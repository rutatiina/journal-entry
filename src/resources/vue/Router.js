const Index = () => import('./components/l-limitless-bs4/Index');
const Form = () => import('./components/l-limitless-bs4/Form');
const Show = () => import('./components/l-limitless-bs4/Show');
const SideBarLeft = () => import('./components/l-limitless-bs4/SideBarLeft');
const SideBarRight = () => import('./components/l-limitless-bs4/SideBarRight');

const routes = [

    {
        path: '/journal-entries',
        components: {
            default: Index,
            //'sidebar-left': ComponentSidebarLeft,
            //'sidebar-right': ComponentSidebarRight
        },
        meta: {
            title: 'Financial Accounting :: Journal Entries',
            metaTags: [
                {
                    name: 'description',
                    content: 'Journal Entries'
                },
                {
                    property: 'og:description',
                    content: 'Journal Entries'
                }
            ]
        }
    },
    {
        path: '/journal-entries/create',
        components: {
            default: Form,
            //'sidebar-left': ComponentSidebarLeft,
            //'sidebar-right': ComponentSidebarRight
        },
        meta: {
            title: 'Financial Accounting :: Journal Entries :: Create',
            metaTags: [
                {
                    name: 'description',
                    content: 'Create Journal Entry'
                },
                {
                    property: 'og:description',
                    content: 'Create Journal Entry'
                }
            ]
        }
    },
    {
        path: '/journal-entries/:id',
        components: {
            default: Show,
            'sidebar-left': SideBarLeft,
            'sidebar-right': SideBarRight
        },
        meta: {
            title: 'Financial Accounting :: Journal Entries',
            metaTags: [
                {
                    name: 'description',
                    content: 'Journal Entry'
                },
                {
                    property: 'og:description',
                    content: 'Journal Entry'
                }
            ]
        }
    },
    {
        path: '/journal-entries/:id/copy',
        components: {
            default: Form,
        },
        meta: {
            title: 'Financial Accounting :: Journal Entries :: Copy',
            metaTags: [
                {
                    name: 'description',
                    content: 'Copy Journal Entry'
                },
                {
                    property: 'og:description',
                    content: 'Copy Journal Entry'
                }
            ]
        }
    },
    {
        path: '/journal-entries/:id/edit',
        components: {
            default: Form,
        },
        meta: {
            title: 'Financial Accounting :: Journal Entries :: Edit',
            metaTags: [
                {
                    name: 'description',
                    content: 'Edit Journal Entry'
                },
                {
                    property: 'og:description',
                    content: 'Edit Journal Entry'
                }
            ]
        }
    }

];

export default routes;
