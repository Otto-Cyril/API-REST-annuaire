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

const withNumbers = (p) => {
  const numerosGarde = sortNumbers(p.numerosGarde)
  return { ...p, numerosGarde, numeros: numbersByType(numerosGarde), periodes: [] }
}

// Gardes -> personnes ; une personne avec plusieurs gardes se chevauchant n'apparaît qu'une fois (ses périodes sont listées).
export function peopleOnDuty(gardes) {
  const byId = new Map()
  for (const g of gardes) {
    const p = byId.get(g.personnelDeGarde.id) ?? withNumbers(g.personnelDeGarde)
    p.periodes.push({ id: g.id, dateDebut: g.dateDebut, dateFin: g.dateFin })
    byId.set(p.id, p)
  }
  return [...byId.values()]
}

// Personnes -> [{ id, libelle, people }] par service, services triés par nom
export function groupByService(people) {
  const groups = new Map()
  for (const p of people) {
    const g = groups.get(p.service.id) ?? { id: p.service.id, libelle: p.service.libelle, people: [] }
    g.people.push(p)
    groups.set(g.id, g)
  }
  return [...groups.values()].sort((a, b) => a.libelle.localeCompare(b.libelle, 'fr'))
}

// Numéros d'une personne rangés par colonne : { fixe, dect, autres } (fixe et dect : texte, « · » s'il y en a plusieurs ; autres : « Type numéro »)
export function numbersByType(numbers) {
  const of = (type) => numbers.filter((n) => n.type === type).map((n) => n.numero).join(' · ')
  return {
    fixe: of('Fixe'),
    dect: of('DECT'),
    autres: numbers.filter((n) => !NUMBER_ORDER.includes(n.type)).map((n) => `${n.type} ${n.numero}`),
  }
}

// Résumé pour la barre du haut : les max premières personnes avec leur numéro le plus utile (DECT, sinon Fixe, sinon un autre)
export function barSummary(people, max = 2) {
  const items = people.slice(0, max).map((p) => {
    const first = p.numerosGarde[0]
    const dect = p.numerosGarde.find((n) => n.type === 'DECT')
    const n = dect ?? first
    return { id: p.id, libelle: p.libelle, type: n?.type ?? '', numero: n?.numero ?? '' }
  })
  return { items, more: Math.max(0, people.length - max) }
}
