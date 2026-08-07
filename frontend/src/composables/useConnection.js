import { onMounted, onUnmounted } from 'vue'
import { useConnectionStore } from '@/stores/connectionStore'

export function useConnection() {
  const connection = useConnectionStore()

  onMounted(() => {
    connection.init()
  })

  onUnmounted(() => {
    window.removeEventListener('online', connection.setOnline)
    window.removeEventListener('offline', connection.setOffline)
  })

  return {
    connection,
    isOnline: connection.$state.online,
    status: connection.$state.status,
  }
}
