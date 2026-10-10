import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const routes = [
    // Public auth pages
    { path: '/login', component: () => import('../pages/LoginPage.vue'), meta: { guest: true } },
    { path: '/register', component: () => import('../pages/RegisterPage.vue'), meta: { guest: true } },
    { path: '/recover', component: () => import('../pages/RecoverPage.vue'), meta: { guest: true } },
    { path: '/u/:username', component: () => import('../pages/PublicProfilePage.vue') },
    { path: '/offline', component: () => import('../pages/OfflinePage.vue') },

    // Authenticated app
    {
        path: '/app',
        component: () => import('../layouts/AppLayout.vue'),
        meta: { auth: true },
        children: [
            { path: '', redirect: '/app/dashboard' },
            { path: 'dashboard', component: () => import('../pages/DashboardPage.vue') },
            { path: 'onboarding', component: () => import('../pages/OnboardingPage.vue') },
            { path: 'placement', component: () => import('../pages/PlacementPage.vue') },
            { path: 'placement/result', component: () => import('../pages/PlacementResultPage.vue') },
            { path: 'learn', component: () => import('../pages/LearnPage.vue') },
            { path: 'learn/level/:id', component: () => import('../pages/LevelPage.vue') },
            { path: 'lesson/:slug', component: () => import('../pages/LessonPage.vue') },
            { path: 'exercise/:id', component: () => import('../pages/ExercisePage.vue') },
            { path: 'exam/:levelId', component: () => import('../pages/ExamPage.vue') },
            { path: 'vocabulary', component: () => import('../pages/VocabularyPage.vue') },
            { path: 'flashcards', component: () => import('../pages/FlashcardsPage.vue') },
            { path: 'practice', component: () => import('../pages/PracticePage.vue') },
            { path: 'progress', component: () => import('../pages/ProgressPage.vue') },
            { path: 'bookmarks', component: () => import('../pages/BookmarksPage.vue') },
            { path: 'notes', component: () => import('../pages/NotesPage.vue') },
            { path: 'search', component: () => import('../pages/SearchPage.vue') },
            { path: 'settings', component: () => import('../pages/SettingsPage.vue') },
        ],
    },

    // Admin
    {
        path: '/admin',
        component: () => import('../layouts/AdminLayout.vue'),
        meta: { auth: true, admin: true },
        children: [
            { path: '', redirect: '/admin/overview' },
            { path: 'overview', component: () => import('../pages/admin/AdminOverview.vue') },
            { path: 'users', component: () => import('../pages/admin/AdminUsers.vue') },
            { path: 'levels', component: () => import('../pages/admin/AdminLevels.vue') },
            { path: 'courses', component: () => import('../pages/admin/AdminCourses.vue') },
            { path: 'lessons', component: () => import('../pages/admin/AdminLessons.vue') },
            { path: 'lessons/:id', component: () => import('../pages/admin/AdminLessonEditor.vue') },
            { path: 'exercises', component: () => import('../pages/admin/AdminExercises.vue') },
            { path: 'exercises/:id/questions', component: () => import('../pages/admin/AdminQuestions.vue') },
            { path: 'vocabulary', component: () => import('../pages/admin/AdminVocabulary.vue') },
            { path: 'achievements', component: () => import('../pages/admin/AdminAchievements.vue') },
            { path: 'settings', component: () => import('../pages/admin/AdminSettings.vue') },
        ],
    },

    { path: '/:pathMatch(.*)*', component: () => import('../pages/NotFoundPage.vue') },
]

export const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior: () => ({ top: 0 }),
})

router.beforeEach(async (to) => {
    const auth = useAuthStore()
    if (!auth.loaded) await auth.fetchUser()

    if (to.meta.auth && !auth.isAuthenticated) {
        return { path: '/login', query: { redirect: to.fullPath } }
    }
    if (to.meta.admin && !auth.isAdmin) {
        return '/app/dashboard'
    }
    if (to.meta.guest && auth.isAuthenticated) {
        return '/app/dashboard'
    }

    // Funnel: onboarding, then placement, before the learning area.
    if (to.meta.auth && auth.isAuthenticated && !auth.isAdmin) {
        const needsOnboarding = !auth.user.onboarded
        const needsPlacement = auth.user.onboarded && !auth.user.placement_done
        const exempt = ['/app/onboarding', '/app/placement', '/app/placement/result', '/app/settings']
        const isExempt = exempt.some((p) => to.path.startsWith(p))
        if (needsOnboarding && !isExempt) return '/app/onboarding'
        if (needsPlacement && !isExempt && !to.path.startsWith('/app/dashboard') && !to.path.startsWith('/app/learn')) {
            // allow dashboard/learn browsing at Beginner before placement
        }
    }
    return true
})
