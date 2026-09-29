import { createRouter, createWebHistory } from 'vue-router'
import { useAuth } from './stores/auth'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'home', component: () => import('./views/HomeView.vue'), meta: { title: '' } },
    { path: '/annuaire', name: 'annuaire', component: () => import('./views/AnnuaireView.vue'), meta: { title: 'Annuaire du personnel' } },
    { path: '/personnel/:id(\\d+)', name: 'fiche', component: () => import('./views/FicheView.vue'), props: true, meta: { title: 'Fiche' } },
    { path: '/connexion', name: 'login', component: () => import('./views/LoginView.vue'), meta: { title: 'Connexion' } },
    { path: '/admin', redirect: '/admin/personnel' },
    {
      path: '/admin/personnel/:id(\\d+)/modifier',
      name: 'personnel-edit',
      component: () => import('./views/EditPersonnelView.vue'),
      props: true,
      meta: { admin: true, title: 'Modifier la fiche' },
    },
    {
      path: '/admin/traces',
      name: 'traces',
      component: () => import('./views/TracesView.vue'),
      meta: { admin: true, title: 'Journal des actions' },
    },
    {
      path: '/admin/:resource',
      name: 'admin',
      component: () => import('./views/AdminView.vue'),
      props: true,
      meta: { admin: true, title: 'Administration' },
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
  scrollBehavior: () => ({ top: 0 }),
})

router.beforeEach((to) => {
  if (to.meta.admin && !useAuth().isAdmin) return { name: 'login', query: { redirect: to.fullPath } }
})

// Titre d'onglet par défaut ; la fiche et l'admin l'affinent une fois leurs données connues.
export const APP_TITLE = 'Annuaire des gardes'
export const pageTitle = (part) => (part ? `${part} – ${APP_TITLE}` : APP_TITLE)
router.afterEach((to) => {
  document.title = pageTitle(to.meta.title)
})

export default router
