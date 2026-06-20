import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { timeAgo, fullDateTime, initialOf, avatarColor } from './format'

describe('initialOf', () => {
  it('ritorna l’iniziale maiuscola', () => {
    expect(initialOf('marco')).toBe('M')
    expect(initialOf('  giulia')).toBe('G')
  })
  it('fallback a ? per nomi vuoti/nulli', () => {
    expect(initialOf('')).toBe('?')
    expect(initialOf(null)).toBe('?')
    expect(initialOf(undefined)).toBe('?')
  })
})

describe('avatarColor', () => {
  it('è deterministico per lo stesso nome', () => {
    expect(avatarColor('Marco')).toBe(avatarColor('Marco'))
  })
  it('usa solo la palette brand/accent (niente blu)', () => {
    const palette = ['#6c5ce7', '#5a45d9', '#9580ea', '#ff7675', '#f85050', '#b8acf2']
    for (const name of ['Marco', 'Giulia', 'Anna', 'Luca', 'Sara', 'X', '', 'Zzz']) {
      expect(palette).toContain(avatarColor(name))
    }
  })
})

describe('timeAgo', () => {
  beforeEach(() => {
    // Congela "adesso" a una data nota.
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-06-18T12:00:00'))
  })
  afterEach(() => vi.useRealTimers())

  it('gestisce valori vuoti', () => {
    expect(timeAgo('')).toBe('')
    expect(timeAgo(null)).toBe('')
  })
  it('“adesso” entro 45s', () => {
    expect(timeAgo('2026-06-18 11:59:30')).toBe('adesso')
  })
  it('minuti e ore', () => {
    expect(timeAgo('2026-06-18 11:58:00')).toBe('2 minuti fa')
    expect(timeAgo('2026-06-18 11:00:00')).toBe('1 ora fa')
    expect(timeAgo('2026-06-18 09:00:00')).toBe('3 ore fa')
  })
  it('“ieri” e giorni', () => {
    expect(timeAgo('2026-06-17 12:00:00')).toBe('ieri')
    expect(timeAgo('2026-06-15 12:00:00')).toBe('3 giorni fa')
  })
})

describe('fullDateTime', () => {
  it('ritorna stringa non vuota per data valida', () => {
    expect(fullDateTime('2026-07-12 15:30:00')).not.toBe('')
  })
  it('vuoto per input non valido', () => {
    expect(fullDateTime('')).toBe('')
    expect(fullDateTime('non-una-data')).toBe('')
  })
})
