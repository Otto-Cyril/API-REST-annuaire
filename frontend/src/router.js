import { createRouter, createWebHistory } from 'vue-router'
import { useAuth } from './stores/auth'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'home', component: () => import('./views/HomeView.vue') },
    { path: '/personnel/:id(\\d+)', name: 'fiche', component: () => import('./views/FicheView.vue'), props: true },
    { path: '/connexion', name: 'login', component: () => import('./views/LoginView.vue') },
    { path: '/admin', redirect: '/admin/personnel' },
    {
      path: '/admin/:resource',
      name: 'admin',
      component: () => import('./views/AdminView.vue'),
      props: true,
      meta: { admin: true },
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
  scrollBehavior: () => ({ top: 0 }),
})

router.beforeEach((to) => {
  if (to.meta.admin && !useAuth().isAdmin) return { name: 'login', query: { redirect: to.fullPath } }
})

export default router
