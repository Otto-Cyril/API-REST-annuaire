// Description des ressources administrables : colonnes du tableau et champs du formulaire.
// `toForm` convertit un élément lu (relations imbriquées) vers les champs envoyés à l'API.
// `options` : ressource dont on charge la liste pour un <select> (valeur = id).
// `wide` : champ affiché sur toute la largeur du formulaire (sinon deux colonnes).
// `undoable` : la suppression peut être annulée en recréant l'élément (aucune donnée liée ne dépend de lui ; il reçoit un nouvel id).

// « 2026-06-10 » -> « 10/06/2026 »
const frDate = (iso) => (iso ? iso.split('-').reverse().join('/') : '')

export const resources = {
  'numeros-urgence': {
    title: "Numéros d'urgence",
    path: '/numeros-urgence',
    undoable: true,
    columns: [
      { key: 'libelle', label: 'Libellé' },
      { key: 'numero', label: 'Numéro' },
    ],
    fields: [
      { key: 'libelle', label: 'Libellé', max: 50 },
      { key: 'numero', label: 'Numéro', max: 50 },
    ],
  },
  personnel: {
    title: 'Gestion du personnel de garde',
    path: '/personnel',
    paginated: true,
    columns: [
      { key: 'libelle', label: 'Nom' },
      { key: 'username', label: 'Identifiant AD' },
      { key: 'service', label: 'Service', get: (r) => r.service?.libelle },
      { key: 'metier', label: 'Métier', get: (r) => r.metier?.libelle },
    ],
    // Le nom, le service et le métier viennent de l'AD à partir de l'identifiant : seul l'identifiant est saisi.
    fields: [
      { key: 'username', label: 'Identifiant AD', type: 'ad', max: 50, wide: true, hint: "Cherchez la personne par son nom : le nom, le service et le métier sont lus automatiquement dans l'AD." },
    ],
    toForm: (r) => ({ username: r.username }),
  },
  gardes: {
    title: 'Planning des gardes',
    path: '/gardes',
    undoable: true,
    columns: [
      { key: 'personnel', label: 'Personnel', get: (r) => r.personnelDeGarde?.libelle },
      { key: 'dateDebut', label: 'Du', get: (r) => frDate(r.dateDebut) },
      { key: 'dateFin', label: 'Au (inclus)', get: (r) => frDate(r.dateFin) },
    ],
    fields: [
      {
        key: 'personnelDeGardeId',
        label: 'Personnel',
        options: 'personnel',
        optionLabel: 'libelle',
        wide: true,
        // /api/personnel est paginé (100 max par page) : on charge la première page.
        params: { limit: 100 },
      },
      { key: 'dateDebut', label: 'Premier jour de garde', type: 'date' },
      { key: 'dateFin', label: 'Dernier jour de garde (inclus)', type: 'date' },
    ],
    toForm: (r) => ({ personnelDeGardeId: r.personnelDeGarde?.id, dateDebut: r.dateDebut, dateFin: r.dateFin }),
  },
  'numeros-garde': {
    title: 'Numéros de garde',
    path: '/numeros-garde',
    undoable: true,
    columns: [
      { key: 'numero', label: 'Numéro' },
      { key: 'type', label: 'Type' },
      { key: 'personnel', label: 'Personnel', get: (r) => r.personnelDeGarde?.libelle },
    ],
    fields: [
      { key: 'numero', label: 'Numéro', max: 50 },
      { key: 'type', label: 'Type', max: 50 },
      {
        key: 'personnelDeGardeId',
        label: 'Personnel',
        options: 'personnel',
        optionLabel: 'libelle',
        wide: true,
        // /api/personnel est paginé (100 max par page) : on charge la première page.
        params: { limit: 100 },
      },
    ],
    toForm: (r) => ({ numero: r.numero, type: r.type, personnelDeGardeId: r.personnelDeGarde?.id }),
  },
}
