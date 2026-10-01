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
    ['Infectiologue référent covid', 'virus'],
    ['Pompier', 'flame'],
    ['SAMU', 'ambulance'],
    ['Police municipale', 'shield'],
    ['Pharmacie de garde', 'pill'],
    ['Laboratoire de biologie', 'drop'],
    ['Maintenance technique', 'wrench'],
    ['Support informatique', 'computer'],
    ['Maternité', 'baby'],
    ['Bureau des admissions', 'bed'],
    ['Pneumologue', 'stethoscope'],
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

describe('iconOf', () => {
  it("préfère l'icône choisie, sinon déduit du libellé", async () => {
    const { iconOf, URGENCE_ICONS } = await import('./urgence')
    expect(iconOf({ libelle: 'Standard', icone: 'bed' })).toBe('bed')
    expect(iconOf({ libelle: 'Incendie', icone: null })).toBe('flame')
    expect(iconOf({ libelle: 'Standard' })).toBe('phone')
    expect(new Set(URGENCE_ICONS.map((i) => i.value)).size).toBe(URGENCE_ICONS.length)
  })
})
