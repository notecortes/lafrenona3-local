import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import './styles/base.css'
import '../pwa-register'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)

const authStore = pinia.use(store => {
  store.initTheme()
  store.initLocale()
  return store
})

app.mount('#app')
