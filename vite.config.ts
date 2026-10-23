import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
  plugins: [react(), tailwindcss()],
  base: '/assets/modules/socialfeed/',
  build: {
    outDir: '../../public/assets/modules/socialfeed',
    emptyOutDir: true,
    manifest: true,
    sourcemap: true,
    lib: {
      entry: 'src/components/pages/index.ts',
      formats: ['es'],
      fileName: (format) => `socialfeed-screens.[hash].js`,
    },
    rollupOptions: {
      external: ['react', 'react-dom', '@tanstack/react-query', 'antd', '@ant-design/icons', 'lucide-react'],
      output: {
        globals: {
          react: 'React',
          'react-dom': 'ReactDOM',
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
