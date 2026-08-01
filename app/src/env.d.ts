/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_API_URL: string
  /** URL del panel de administración (enlaces del header y de editar). */
  readonly VITE_ADMIN_URL?: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}
