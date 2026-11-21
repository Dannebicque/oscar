import { describe, expect, it } from 'vitest'
import { swipeTarget } from '../composables/useSwipeNav'

describe('balayage entre vues (U-02)', () => {
  it('glisser vers la gauche ouvre la vue suivante, vers la droite la précédente, dans l’ordre de la barre', () => {
    expect(swipeTarget(-150, 10, 'suivis')).toBe('agenda')
    expect(swipeTarget(150, 10, 'suivis')).toBe('mails')
    expect(swipeTarget(-150, 0, 'home')).toBe('mails')
  })
  it('ignore un geste trop court, trop vertical, aux extrémités, ou sur une route qui n’est pas une vue principale', () => {
    expect(swipeTarget(-40, 0, 'suivis')).toBeNull()
    expect(swipeTarget(-120, 100, 'suivis')).toBeNull() // plutôt un défilement
    expect(swipeTarget(150, 0, 'home')).toBeNull()
    expect(swipeTarget(-150, 0, 'tasks')).toBeNull()
    expect(swipeTarget(-150, 0, 'compose')).toBeNull()
    expect(swipeTarget(-150, 0, undefined)).toBeNull()
  })
})
