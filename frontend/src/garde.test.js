import { describe, it, expect } from 'vitest'
import { frDate, today, relativeDay, sortNumbers, peopleOnDuty, groupByService } from './garde'

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
