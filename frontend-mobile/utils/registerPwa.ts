export interface PwaInstallState {
  isStandalone: boolean;
  canPromptNative: boolean;
  isIos: boolean;
  promptInstall: () => Promise<void>;
}

export function registerPwa() {
  // No-op on native platforms
}

export function usePwaInstall(): PwaInstallState {
  return {
    isStandalone: true,
    canPromptNative: false,
    isIos: false,
    promptInstall: async () => {},
  };
}

