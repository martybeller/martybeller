// @ts-check
import { defineConfig } from 'astro/config';

import tailwindcss from '@tailwindcss/vite';

const isGitHubPages = process.env.GITHUB_ACTIONS === 'true';

// https://astro.build/config
export default defineConfig({
  site: isGitHubPages
    ? 'https://martybeller.github.io/martybeller'
    : 'http://localhost:4321',
  base: isGitHubPages ? '/martybeller' : '/',
  vite: {
    plugins: [tailwindcss()]
  }
});