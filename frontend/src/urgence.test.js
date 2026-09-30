import { describe, it, expect } from 'vitest'
import { urgenceIcon } from './urgence'

describe('urgenceIcon', () => {
  it.each([
    ['Urgence vitale', 'plus'],
    ['Incendie', 'flame'],
    ['Poste de sécurité', 'shield'],
    ['SOS Ménage', 'trash'],
    ['SOS collecte de déchets ménagers ou de soins', 'trash'],
    ['Administrateur de garde', 'user'],
    ['Cardiologue', 'heart'],
    ['Chirurgien cardiaque', 'heart'],
    ['Perfusionniste', 'heart'],
    ['Chirurgien urologie', 'stethoscope'],
    ['Gastro-entérologue', 'stethoscope'],
    ['Infectiologue référent covid', 'stethoscope'],
  ])('%s -> %s', (libelle, icon) => {
    expect(urgenceIcon(libelle)).toBe(icon)
  })

  it('prend une icône de téléphone par défaut', () => {
    expect(urgenceIcon('Standard')).toBe('phone')
    expect(urgenceIcon("L'AMP")).toBe('phone')
    expect(urgenceIcon('')).toBe('phone')
    expect(urgenceIcon(undefined)).toBe('phone')
  })
})
