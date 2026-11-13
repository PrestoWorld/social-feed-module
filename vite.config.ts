import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
  plugins: [react(), tailwindcss()],
  esbuild: {
    jsx: 'automatic',
    jsxImportSource: 'react',
  },
  base: '/assets/modules/socialfeed/',
  build: {
    outDir: '../../public/assets/modules/socialfeed',
    emptyOutDir: true,
    manifest: true,
    sourcemap: true,
    lib: {
      entry: 'src/components/pages/index.ts',
      formats: ['iife'],
      name: 'SocialFeedScreens',
      fileName: (format) => `socialfeed-screens.[hash].js`,
    },
    rollupOptions: {
      external: [
        'react',
        'react-dom',
        'react-dom/client',
        'react/jsx-runtime',
        'react/jsx-dev-runtime',
        '@tanstack/react-query',
        'antd',
        '@ant-design/icons',
        'lucide-react',
      ],
      output: {
        globals: {
          react: 'React',
          'react-dom': 'ReactDOM',
          'react-dom/client': 'ReactDOMClient',
          'react/jsx-runtime': 'ReactJSXRuntime',
          'react/jsx-dev-runtime': 'ReactJSXRuntime',
          '@tanstack/react-query': 'ReactQuery',
          antd: 'antd',
          '@ant-design/icons': 'AntDesignIcons',
          'lucide-react': 'LucideReact',
        },
      },
    },
  },
  resolve: {
    conditions: ['development', 'browser'],
  },
});