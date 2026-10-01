import { describe, it, expect } from 'vitest'
import { splitMatch, withServiceHeaders } from './list'

describe('splitMatch', () => {
  it('met en évidence sans tenir compte de la casse ni des accents', () => {
    expect(splitMatch('Inès Chevalier', 'ines')).toEqual([
      { text: 'Inès', match: true },
      { text: ' Chevalier', match: false },
    ])
    expect(splitMatch('Médecin', 'MED')).toEqual([
      { text: 'Méd', match: true },
      { text: 'ecin', match: false },
    ])
  })

  it('trouve plusieurs occurrences', () => {
    expect(splitMatch('Banane', 'an')).toEqual([
      { text: 'B', match: false },
      { text: 'an', match: true },
      { text: 'an', match: true },
      { text: 'e', match: false },
    ])
  })

  it('renvoie le texte entier sans recherche ou sans correspondance', () => {
    expect(splitMatch('Paul', '')).toEqual([{ text: 'Paul', match: false }])
    expect(splitMatch('Paul', '  ')).toEqual([{ text: 'Paul', match: false }])
    expect(splitMatch('Paul', 'z')).toEqual([{ text: 'Paul', match: false }])
  })

  it('ne traite pas la requête comme une expression régulière', () => {
    expect(splitMatch('a.b (c)', '.')).toEqual([
      { text: 'a', match: false },
      { text: '.', match: true },
      { text: 'b (c)', match: false },
    ])
  })
})

describe('withServiceHeaders', () => {
  const p = (id, service) => ({ id, service })
  const serviceOf = (x) => x.service

  it('ajoute un en-tête à chaque changement de service', () => {
    const a = { id: 1, libelle: 'Cardiologie' }
    const b = { id: 2, libelle: 'Urgences' }
    const items = [p(1, a), p(2, a), p(3, b)]
    expect(withServiceHeaders(items, serviceOf)).toEqual([
      { header: true, id: 'h-1', label: 'Cardiologie' },
      items[0],
      items[1],
      { header: true, id: 'h-2', label: 'Urgences' },
      items[2],
    ])
  })

  it('renvoie une liste vide pour une liste vide', () => {
    expect(withServiceHeaders([], serviceOf)).toEqual([])
  })
})
