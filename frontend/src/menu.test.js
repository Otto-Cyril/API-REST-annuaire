import { describe, it, expect } from 'vitest'
import { buildNavGroups, activeGroupId } from './menu'
import { tokenUsername } from './jwt'

const res = (...keys) => Object.fromEntries(keys.map((k) => [k, { title: k.toUpperCase() }]))

describe('buildNavGroups', () => {
  const groups = buildNavGroups(res('services', 'metiers', 'numeros-urgence', 'personnel', 'gardes', 'numeros-garde'))

  it('regroupe les ressources et ajoute le journal dans « Suivi »', () => {
    expect(groups.map((g) => [g.id, g.items.map((i) => i.id)])).toEqual([
      ['personnel', ['personnel']],
      ['gardes', ['gardes', 'numeros-garde', 'numeros-urgence']],
      ['referentiels', ['services', 'metiers']],
      ['suivi', ['traces']],
    ])
  })

  it('range une ressource inconnue dans « Autres » et omet les groupes vides', () => {
    const g = buildNavGroups(res('services', 'nouveau'))
    expect(g.map((x) => x.id)).toEqual(['referentiels', 'suivi', 'autres'])
    expect(g[2].items[0].to).toEqual({ name: 'admin', params: { resource: 'nouveau' } })
  })
})

describe('activeGroupId', () => {
  const groups = buildNavGroups(res('services', 'gardes'))

  it('trouve le groupe de la ressource ou du journal affiché', () => {
    expect(activeGroupId(groups, { name: 'admin', params: { resource: 'gardes' } })).toBe('gardes')
    expect(activeGroupId(groups, { name: 'traces', params: {} })).toBe('suivi')
  })

  it('renvoie null hors administration ou pour une ressource hors menu', () => {
    expect(activeGroupId(groups, { name: 'garde', params: {} })).toBeNull()
    expect(activeGroupId(groups, { name: 'admin', params: { resource: 'personnes' } })).toBeNull()
  })
})

describe('tokenUsername', () => {
  const token = (payload) => `x.${btoa(JSON.stringify(payload)).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '')}.y`

  it('lit l\'identifiant dans la charge utile', () => {
    expect(tokenUsername(token({ username: 'presmed5255' }))).toBe('presmed5255')
  })

  it('renvoie une chaîne vide si le token est absent, illisible ou sans identifiant', () => {
    expect(tokenUsername(null)).toBe('')
    expect(tokenUsername('abc')).toBe('')
    expect(tokenUsername(token({ exp: 1 }))).toBe('')
  })
})
