import { registerSW } from 'virtual:pwa-register'

const updateSW = registerSW({
  onNeedRefresh() {
    if (confirm('Nueva versión disponible. ¿Actualizar?')) {
      updateSW(true)
    }
  },
  onOfflineReady() {
    console.log('App lista para uso offline')
  },
  onRegistered() {
    console.log('Service Worker registrado')
  },
  onRegisterError(error) {
    console.error('Fallo registro Service Worker:', error)
  }
})

export { updateSW }
