import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'Geo Maps',
  description: 'A beautifully simple, yet deceptively powerful map field for Craft CMS',
  base: '/',

  themeConfig: {
    logo: { src: '/logo.svg', width: 24, height: 24 },
    siteTitle: 'Geo Maps',
    nav: [
      { text: 'Plugin Store', link: 'https://plugins.craftcms.com/', target: '_blank' },
    ],

    sidebar: [
      {
        text: 'Introduction',
        link: '/',
      },
      {
        text: 'Getting Started',
        items: [
          { text: 'Installation', link: '/getting-started/installation' },
          { text: 'Configuration', link: '/getting-started/configuration' },
          { text: 'Usage', link: '/getting-started/usage' },
        ],
      },
      {
        text: 'How-to Guides',
        items: [
          { text: 'Location Search', link: '/how-to/search' },
          { text: 'Querying in GraphQL', link: '/how-to/graphql' },
        ],
      },
      {
        text: 'Rendering',
        items: [
          { text: 'Render Maps', link: '/rendering/render' },
        ],
      },
      {
        text: 'Geo-location',
        items: [
          { text: 'Get User Location', link: '/geolocation/get' },
        ],
      },
    ],

    socialLinks: [
      { icon: 'github', link: 'https://github.com' },
    ],

    footer: {
      message: 'A Craft CMS plugin by <a href="https://suprimgolay.com.np/" target="_blank">Suprim</a>',
    },

    editLink: {
      pattern: 'https://github.com/edit/main/docs/:path',
      text: 'Edit this page on GitHub',
    },

    search: {
      provider: 'local',
    },
  },
})
