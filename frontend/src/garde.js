// Fonctions pures du panneau « Garde en cours » (testées dans garde.test.js).

// « 2026-06-10 » -> « 10/06/2026 »
export const frDate = (iso) => iso.split('-').reverse().join('/')

// Jour du navigateur au format AAAA-MM-JJ (recalculé à chaque chargement : le changement de jour est automatique)
export function today(d = new Date()) {
  return [d.getFullYear(), String(d.getMonth() + 1).padStart(2, '0'), String(d.getDate()).padStart(2, '0')].join('-')
}

// « demain » si la date est le lendemain de todayIso, sinon « le 12/10/2026 »
export function relativeDay(iso, todayIso) {
  const next = new Date(`${todayIso}T00:00:00Z`)
  next.setUTCDate(next.getUTCDate() + 1)
  return iso === next.toISOString().slice(0, 10) ? 'demain' : `le ${frDate(iso)}`
}

// Ordre d'affichage des numéros : Fixe, puis DECT, puis les autres (ordre de saisie conservé)
const NUMBER_ORDER = ['Fixe', 'DECT']
const numberRank = (n) => {
  const i = NUMBER_ORDER.indexOf(n.type)
  return i === -1 ? NUMBER_ORDER.length : i
}
export const sortNumbers = (numbers) => [...numbers].sort((a, b) => numberRank(a) - numberRank(b))

const withSortedNumbers = (p) => ({ ...p, numerosGarde: sortNumbers(p.numerosGarde), periodes: [] })

// Gardes -> personnes ; une personne avec plusieurs gardes se chevauchant n'apparaît qu'une fois (ses périodes sont listées).
export function peopleOnDuty(gardes) {
  const byId = new Map()
  for (const g of gardes) {
    const p = byId.get(g.personnelDeGarde.id) ?? withSortedNumbers(g.personnelDeGarde)
    p.periodes.push({ id: g.id, dateDebut: g.dateDebut, dateFin: g.dateFin })
    byId.set(p.id, p)
  }
  return [...byId.values()]
}

// Personnes -> [{ id, libelle, people }] par service, services triés par nom
export function groupByService(people) {
  const groups = new Map()
  for (const p of people) {
    const service = p.service ?? { id: '', libelle: 'Sans service' }
    const g = groups.get(service.id) ?? { id: service.id, libelle: service.libelle, people: [] }
    g.people.push(p)
    groups.set(g.id, g)
  }
  return [...groups.values()].sort((a, b) => a.libelle.localeCompare(b.libelle, 'fr'))
}
