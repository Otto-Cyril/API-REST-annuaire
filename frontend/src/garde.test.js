import { describe, it, expect } from 'vitest'
import { frDate, today, relativeDay, sortNumbers, peopleOnDuty, groupByService, numbersByType, barSummary } from './garde'

const person = (id, libelle, service, numerosGarde = []) => ({ id, libelle, service, metier: { id: 1, libelle: 'Médecin' }, numerosGarde })
const garde = (id, p, dateDebut, dateFin = dateDebut) => ({ id, personnelDeGarde: p, dateDebut, dateFin })

describe('dates', () => {
  it('formate une date ISO à la française', () => {
    expect(frDate('2026-06-10')).toBe('10/06/2026')
  })

  it('renvoie le jour local au format AAAA-MM-JJ', () => {
    expect(today(new Date(2026, 0, 5))).toBe('2026-01-05')
  })

  it('dit « demain » pour le lendemain, y compris en fin de mois et d\'année', () => {
    expect(relativeDay('2026-06-11', '2026-06-10')).toBe('demain')
    expect(relativeDay('2026-07-01', '2026-06-30')).toBe('demain')
    expect(relativeDay('2027-01-01', '2026-12-31')).toBe('demain')
  })

  it('donne la date complète au-delà du lendemain', () => {
    expect(relativeDay('2026-10-12', '2026-10-01')).toBe('le 12/10/2026')
  })
})

describe('sortNumbers', () => {
  it('place Fixe puis DECT avant les autres et garde l\'ordre de saisie des autres', () => {
    const types = sortNumbers([{ type: 'Astreinte' }, { type: 'DECT' }, { type: 'Mobile' }, { type: 'Fixe' }]).map((n) => n.type)
    expect(types).toEqual(['Fixe', 'DECT', 'Astreinte', 'Mobile'])
  })

  it('ne modifie pas le tableau d\'origine', () => {
    const src = [{ type: 'DECT' }, { type: 'Fixe' }]
    sortNumbers(src)
    expect(src[0].type).toBe('DECT')
  })
})

describe('peopleOnDuty', () => {
  it('fusionne les gardes d\'une même personne et liste ses périodes', () => {
    const martin = person(1, 'Dr Martin', { id: 1, libelle: 'Urgences' }, [{ type: 'DECT' }, { type: 'Fixe' }])
    const res = peopleOnDuty([garde(10, martin, '2026-06-10', '2026-06-12'), garde(11, martin, '2026-06-11')])

    expect(res).toHaveLength(1)
    expect(res[0].periodes.map((p) => p.id)).toEqual([10, 11])
    expect(res[0].numerosGarde.map((n) => n.type)).toEqual(['Fixe', 'DECT'])
  })

  it('renvoie une liste vide sans garde', () => {
    expect(peopleOnDuty([])).toEqual([])
  })
})

describe('groupByService', () => {
  it('regroupe par service, services triés par nom, personnes dans l\'ordre reçu', () => {
    const urg = { id: 1, libelle: 'Urgences' }
    const car = { id: 2, libelle: 'Cardiologie' }
    const groups = groupByService([person(1, 'Dr A', urg), person(2, 'Dr B', car), person(3, 'Dr C', urg)])

    expect(groups.map((g) => g.libelle)).toEqual(['Cardiologie', 'Urgences'])
    expect(groups[1].people.map((p) => p.libelle)).toEqual(['Dr A', 'Dr C'])
  })

  it('trie les services avec accents selon le français', () => {
    const groups = groupByService([person(1, 'A', { id: 1, libelle: 'Réanimation' }), person(2, 'B', { id: 2, libelle: 'Radiologie' })])
    expect(groups.map((g) => g.libelle)).toEqual(['Radiologie', 'Réanimation'])
  })

  it('renvoie une liste vide sans personne', () => {
    expect(groupByService([])).toEqual([])
  })
})

describe('numbersByType', () => {
  it('range les numéros par colonne Fixe / DECT / autres', () => {
    const res = numbersByType([
      { type: 'Fixe', numero: '01 23 45 67 12' },
      { type: 'DECT', numero: '4101' },
      { type: 'Astreinte', numero: '01 43 21 00 12' },
    ])
    expect(res).toEqual({ fixe: '01 23 45 67 12', dect: '4101', autres: ['Astreinte 01 43 21 00 12'] })
  })

  it('laisse vide une colonne sans numéro et joint les doublons', () => {
    const res = numbersByType([{ type: 'DECT', numero: '4101' }, { type: 'DECT', numero: '4102' }])
    expect(res).toEqual({ fixe: '', dect: '4101 · 4102', autres: [] })
  })

  it('est calculé sur les personnes de garde', () => {
    const martin = person(1, 'Dr Martin', { id: 1, libelle: 'Urgences' }, [{ type: 'DECT', numero: '4101' }, { type: 'Fixe', numero: '0123' }])
    expect(peopleOnDuty([garde(10, martin, '2026-06-10')])[0].numeros).toEqual({ fixe: '0123', dect: '4101', autres: [] })
  })
})

describe('barSummary', () => {
  const mk = (id, libelle, numerosGarde) => person(id, libelle, { id: 1, libelle: 'Urgences' }, numerosGarde)

  it('montre le DECT de chaque personne et compte les suivantes', () => {
    const people = [
      mk(1, 'Dr A', [{ type: 'Fixe', numero: '01' }, { type: 'DECT', numero: '4101' }]),
      mk(2, 'Dr B', [{ type: 'DECT', numero: '4102' }]),
      mk(3, 'Dr C', [{ type: 'DECT', numero: '4103' }]),
    ]
    expect(barSummary(people)).toEqual({
      items: [
        { id: 1, libelle: 'Dr A', type: 'DECT', numero: '4101' },
        { id: 2, libelle: 'Dr B', type: 'DECT', numero: '4102' },
      ],
      more: 1,
    })
  })

  it('se rabat sur le premier numéro sans DECT, et reste vide sans numéro', () => {
    const res = barSummary([mk(1, 'Dr A', [{ type: 'Fixe', numero: '01' }]), mk(2, 'Dr B', [])])
    expect(res.items[0]).toMatchObject({ type: 'Fixe', numero: '01' })
    expect(res.items[1]).toMatchObject({ type: '', numero: '' })
    expect(res.more).toBe(0)
  })

  it('renvoie un résumé vide sans personne', () => {
    expect(barSummary([])).toEqual({ items: [], more: 0 })
  })
})
