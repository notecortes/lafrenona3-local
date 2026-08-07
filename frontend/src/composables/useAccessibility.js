import { ref, onMounted, onUnmounted } from 'vue'

export function useAccessibility() {
  const focusTrap = ref(null)
  const previousFocus = ref(null)
  const ariaLiveRegion = ref(null)

  function announce(message) {
    if (ariaLiveRegion.value) {
      ariaLiveRegion.value.textContent = ''
      setTimeout(() => {
        ariaLiveRegion.value.textContent = message
      }, 100)
    }
  }

  function trapFocus(element) {
    previousFocus.value = document.activeElement
    focusTrap.value = element

    const focusableElements = element.querySelectorAll(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    )

    const firstFocusable = focusableElements[0]
    const lastFocusable = focusableElements[focusableElements.length - 1]

    function handleKeyDown(e) {
      if (e.key === 'Tab') {
        if (e.shiftKey) {
          if (document.activeElement === firstFocusable) {
            e.preventDefault()
            lastFocusable.focus()
          }
        } else {
          if (document.activeElement === lastFocusable) {
            e.preventDefault()
            firstFocusable.focus()
          }
        }
      }

      if (e.key === 'Escape') {
        releaseFocus()
      }
    }

    element.addEventListener('keydown', handleKeyDown)

    setTimeout(() => {
      firstFocusable?.focus()
    }, 100)

    return () => {
      element.removeEventListener('keydown', handleKeyDown)
    }
  }

  function releaseFocus() {
    if (focusTrap.value) {
      focusTrap.value = null
    }
    if (previousFocus.value) {
      previousFocus.value.focus()
      previousFocus.value = null
    }
  }

  function isReducedMotion() {
    const mediaQuery = window.matchMedia('(prefers-reduced-motion: reduce)')
    return mediaQuery.matches
  }

  onMounted(() => {
    const region = document.createElement('div')
    region.setAttribute('aria-live', 'polite')
    region.setAttribute('aria-atomic', 'true')
    region.className = 'sr-only'
    region.id = 'aria-live-region'
    document.body.appendChild(region)
    ariaLiveRegion.value = region
  })

  onUnmounted(() => {
    if (ariaLiveRegion.value) {
      ariaLiveRegion.value.remove()
    }
  })

  return {
    announce,
    trapFocus,
    releaseFocus,
    isReducedMotion,
    ariaLiveRegion,
  }
}
