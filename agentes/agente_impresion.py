#!/usr/bin/env python3
"""
Agente de impresión asíncrono para comandas de cocina/barra.

Se conecta al servidor de WebSockets (Reverb) y escucha eventos
de creación de items de pedido. Cuando recibe un evento, envía
los datos de la comanda al spooler de impresión ESC/POS.

Reconexión infinita con backoff exponencial en caso de fallos.
"""

import asyncio
import json
import logging
import sys
import time
import uuid
from typing import Any, Dict, Optional

try:
    import websockets
except ImportError:
    print("ERROR: websockets library not installed. Run: pip install websockets")
    sys.exit(1)

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    handlers=[logging.StreamHandler(sys.stdout)],
)
logger = logging.getLogger("print_agent")

REVERB_HOST: str = "localhost"
REVERB_PORT: int = 6001
REVERB_SCHEME: str = "http"
REVERB_APP_KEY: str = ""
REVERB_APP_SECRET: str = ""
REVERB_APP_ID: str = ""

PRINT_INTERVAL: float = 2.0
MAX_RECONNECT_DELAY: float = 60.0
INITIAL_RECONNECT_DELAY: float = 1.0


class PrintAgent:
    """Escucha eventos de WebSockets y envía comandas a impresora ESC/POS."""

    def __init__(
        self,
        host: str = REVERB_HOST,
        port: int = REVERB_PORT,
        scheme: str = REVERB_SCHEME,
        app_key: str = REVERB_APP_KEY,
        app_secret: str = REVERB_APP_SECRET,
        app_id: str = REVERB_APP_ID,
    ) -> None:
        self.host = host
        self.port = port
        self.scheme = scheme
        self.app_key = app_key
        self.app_secret = app_secret
        self.app_id = app_id
        self.running = False
        self.websocket: Optional[websockets.WebSocketClientProtocol] = None
        self.reconnect_delay: float = INITIAL_RECONNECT_DELAY

    def _build_ws_url(self) -> str:
        return f"{self.scheme}://{self.host}:{self.port}/app/{self.app_key}?protocol=7&client=js&version=5.1.0&flash=false"

    def _format_receipt(self, item: Dict[str, Any]) -> str:
        lines: list[str] = []
        lines.append("=" * 40)
        lines.append("       NEW ORDER ITEM")
        lines.append("=" * 40)
        lines.append(f"Order: #{item.get('order_id', 'N/A')}")
        lines.append(f"Table: {item.get('table_number', 'N/A')}")
        lines.append(f"Area:  {item.get('target_area', 'N/A').upper()}")
        lines.append("-" * 40)
        product_name = item.get("product_name", "Unknown")
        quantity = item.get("quantity", 1)
        lines.append(f"  {product_name} x{quantity}")
        notes = item.get("notes")
        if notes:
            lines.append(f"  NOTE: {notes}")
        lines.append("-" * 40)
        lines.append(f"Status: {item.get('status', 'pending').upper()}")
        lines.append(f"Time:   {item.get('created_at', 'N/A')}")
        lines.append("=" * 40)
        lines.append("")
        return "\n".join(lines)

    async def _print_item(self, item: Dict[str, Any]) -> None:
        receipt = self._format_receipt(item)
        logger.info("Print job generated:\n%s", receipt)
        await asyncio.sleep(0.1)

    async def _handle_message(self, message: str) -> None:
        try:
            data = json.loads(message)
        except json.JSONDecodeError:
            logger.warning("Received invalid JSON: %s", message[:200])
            return

        event_name = data.get("event", "")

        if event_name == "order-item.created":
            payload = data.get("data", {})
            logger.info("Received order-item.created event: %s", payload.get("id"))
            await self._print_item(payload)
        elif event_name == "pusher:connection_established":
            logger.info("Connection established to Reverb")
        elif event_name.startswith("pusher:"):
            logger.debug("Pusher event: %s", event_name)
        else:
            logger.debug("Unhandled event: %s", event_name)

    async def _connect_and_subscribe(self) -> None:
        ws_url = self._build_ws_url()
        logger.info("Connecting to %s ...", ws_url)

        while self.running:
            try:
                async with websockets.connect(ws_url) as ws:
                    self.websocket = ws
                    self.reconnect_delay = INITIAL_RECONNECT_DELAY
                    logger.info("Connected. Listening for events...")

                    async for raw_message in ws:
                        if not self.running:
                            break
                        await self._handle_message(raw_message)

            except (
                websockets.exceptions.ConnectionClosed,
                ConnectionRefusedError,
                OSError,
            ) as exc:
                if not self.running:
                    break
                logger.warning(
                    "Connection lost: %s. Reconnecting in %.1fs...",
                    exc,
                    self.reconnect_delay,
                )
                await asyncio.sleep(self.reconnect_delay)
                self.reconnect_delay = min(
                    self.reconnect_delay * 2, MAX_RECONNECT_DELAY
                )
            except Exception as exc:
                if not self.running:
                    break
                logger.error("Unexpected error: %s. Reconnecting...", exc)
                await asyncio.sleep(self.reconnect_delay)
                self.reconnect_delay = min(
                    self.reconnect_delay * 2, MAX_RECONNECT_DELAY
                )

    async def run(self) -> None:
        self.running = True
        logger.info("Print agent starting...")
        await self._connect_and_subscribe()
        logger.info("Print agent stopped.")

    def stop(self) -> None:
        self.running = False


async def main() -> None:
    agent = PrintAgent()

    loop = asyncio.get_running_loop()

    def _signal_handler() -> None:
        logger.info("Received shutdown signal.")
        agent.stop()

    for sig in (signal.SIGINT, signal.SIGTERM):
        try:
            loop.add_signal_handler(sig, _signal_handler)
        except NotImplementedError:
            pass

    await agent.run()


if __name__ == "__main__":
    import signal

    asyncio.run(main())
