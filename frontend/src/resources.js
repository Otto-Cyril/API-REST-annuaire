// Description des ressources administrables : colonnes du tableau et champs du formulaire.
// `toForm` convertit un élément lu (relations imbriquées) vers les champs envoyés à l'API.
// `options` : ressource dont on charge la liste pour un <select> (valeur = id).
// `undoable` : la suppression peut être annulée en recréant l'élément (aucune donnée liée ne dépend de lui ; il reçoit un nouvel id).

// « 2026-06-10 » -> « 10/06/2026 »
const frDate = (iso) => (iso ? iso.split('-').reverse().join('/') : '')

export const resources = {
  services: {
    title: 'Services',
    path: '/services',
    columns: [
      { key: 'libelle', label: 'Libellé' },
      { key: 'localisation', label: 'Localisation' },
    ],
    fields: [
      { key: 'libelle', label: 'Libellé', max: 50 },
      { key: 'localisation', label: 'Localisation', max: 50 },
    ],
  },
  metiers: {
    title: 'Métiers',
    path: '/metiers',
    columns: [
      { key: 'libelle', label: 'Libellé' },
      { key: 'nom', label: 'Nom' },
      { key: 'prenom', label: 'Prénom' },
    ],
    fields: [
      { key: 'libelle', label: 'Libellé', max: 50 },
      { key: 'nom', label: 'Nom', max: 50 },
      { key: 'prenom', label: 'Prénom', max: 50 },
    ],
  },
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
      { key: 'libelle', label: 'Libellé' },
      { key: 'service', label: 'Service', get: (r) => r.service?.libelle },
      { key: 'metier', label: 'Métier', get: (r) => r.metier?.libelle },
    ],
    fields: [
      { key: 'libelle', label: 'Libellé', max: 50 },
      { key: 'serviceId', label: 'Service', options: 'services', optionLabel: 'libelle' },
      { key: 'metierId', label: 'Métier', options: 'metiers', optionLabel: 'libelle' },
    ],
    toForm: (r) => ({ libelle: r.libelle, serviceId: r.service?.id, metierId: r.metier?.id }),
  },
  personnes: {
    title: 'Annuaire du personnel',
    path: '/personnes',
    paginated: true,
    hideFromMenu: true, // absent du menu « Administration » (la page reste accessible en /admin/personnes)
    columns: [
      { key: 'nom', label: 'Nom' },
      { key: 'prenom', label: 'Prénom' },
      { key: 'service', label: 'Service', get: (r) => r.service?.libelle },
      { key: 'metier', label: 'Métier', get: (r) => r.metier?.libelle },
      { key: 'email', label: 'E-mail' },
      { key: 'telephone', label: 'Téléphone / DECT' },
    ],
    // optional : champ facultatif (une valeur vide est envoyée comme null) ; type : type de l'<input>.
    fields: [
      { key: 'nom', label: 'Nom', max: 50 },
      { key: 'prenom', label: 'Prénom', max: 50 },
      { key: 'serviceId', label: 'Service', options: 'services', optionLabel: 'libelle' },
      { key: 'metierId', label: 'Métier', options: 'metiers', optionLabel: 'libelle' },
      { key: 'email', label: 'E-mail', max: 100, type: 'email', optional: true },
      { key: 'telephone', label: 'Téléphone / DECT', max: 50, optional: true },
    ],
    toForm: (r) => ({
      nom: r.nom,
      prenom: r.prenom,
      serviceId: r.service?.id,
      metierId: r.metier?.id,
      email: r.email,
      telephone: r.telephone,
    }),
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
        // /api/personnel est paginé (100 max par page) : on charge la première page.
        params: { limit: 100 },
      },
    ],
    toForm: (r) => ({ numero: r.numero, type: r.type, personnelDeGardeId: r.personnelDeGarde?.id }),
  },
}
