import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { useAuthStore } from '@/stores/authStore'
import App from './App.vue'
import router from './router'
import './styles/base.css'
import '../pwa-register'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)

const authStore = useAuthStore()
authStore.initTheme()
authStore.initLocale()

app.use(router)

app.mount('#app')
