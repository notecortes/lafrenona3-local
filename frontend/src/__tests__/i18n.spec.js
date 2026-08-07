import { describe, it, expect } from 'vitest'
import { t, translations, languages } from '@/config/i18n'

describe('i18n', () => {
  it('should have all 8 languages', () => {
    expect(languages).toHaveLength(8)
    expect(languages.map(l => l.code)).toEqual(['es', 'en', 'fr', 'de', 'ca', 'eu', 'val', 'it'])
  })

  it('should translate basic keys in Spanish', () => {
    expect(t('app.name', 'es')).toBe('Menú Digital')
    expect(t('app.loading', 'es')).toBe('Cargando...')
    expect(t('menu.addToCart', 'es')).toBe('Añadir')
  })

  it('should translate basic keys in English', () => {
    expect(t('app.name', 'en')).toBe('Digital Menu')
    expect(t('app.loading', 'en')).toBe('Loading...')
    expect(t('menu.addToCart', 'en')).toBe('Add')
  })

  it('should translate basic keys in French', () => {
    expect(t('app.name', 'fr')).toBe('Menu Digital')
    expect(t('app.loading', 'fr')).toBe('Chargement...')
    expect(t('menu.addToCart', 'fr')).toBe('Ajouter')
  })

  it('should translate basic keys in German', () => {
    expect(t('app.name', 'de')).toBe('Digitales Menü')
    expect(t('app.loading', 'de')).toBe('Laden...')
    expect(t('menu.addToCart', 'de')).toBe('Hinzufügen')
  })

  it('should translate basic keys in Catalan', () => {
    expect(t('app.name', 'ca')).toBe('Menú Digital')
    expect(t('app.loading', 'ca')).toBe('Carregant...')
    expect(t('menu.addToCart', 'ca')).toBe('Afegir')
  })

  it('should translate basic keys in Basque', () => {
    expect(t('app.name', 'eu')).toBe('Menu Digitala')
    expect(t('app.loading', 'eu')).toBe('Kargatzen...')
    expect(t('menu.addToCart', 'eu')).toBe('Gehitu')
  })

  it('should translate basic keys in Valencian', () => {
    expect(t('app.name', 'val')).toBe('Menú Digital')
    expect(t('app.loading', 'val')).toBe('Carregant...')
    expect(t('menu.addToCart', 'val')).toBe('Afegir')
  })

  it('should translate basic keys in Italian', () => {
    expect(t('app.name', 'it')).toBe('Menu Digitale')
    expect(t('app.loading', 'it')).toBe('Caricamento...')
    expect(t('menu.addToCart', 'it')).toBe('Aggiungi')
  })

  it('should handle nested keys', () => {
    expect(t('order.statuses.pending', 'es')).toBe('Pendiente')
    expect(t('order.statuses.pendingDesc', 'es')).toBe('Tu pedido ha sido recibido')
  })

  it('should handle params', () => {
    const result = t('cart.itemCount', 'es', { count: 3 })
    expect(result).toContain('3')
  })

  it('should return key if translation not found', () => {
    const result = t('nonexistent.key', 'es')
    expect(result).toBe('nonexistent.key')
  })

  it('should have translations for all languages', () => {
    const requiredKeys = ['app', 'menu', 'cart', 'product', 'order', 'assistance', 'lang', 'theme', 'status']
    
    for (const lang of languages) {
      for (const key of requiredKeys) {
        expect(translations[lang.code]).toHaveProperty(key)
      }
    }
  })
})