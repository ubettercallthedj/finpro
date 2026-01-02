interface ImportMetaEnv {
  readonly DEV?: boolean
  readonly VITE_API_URL?: string
  readonly [key: string]: any
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}
