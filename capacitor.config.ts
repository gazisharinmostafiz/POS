import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
    appId: process.env.CAPACITOR_ANDROID_APP_ID || 'com.poslab.placeholder',
    appName: 'PosLAB',
    webDir: 'capacitor-www',
    bundledWebRuntime: false,
    server: process.env.CAPACITOR_SERVER_URL
        ? {
            url: process.env.CAPACITOR_SERVER_URL,
            cleartext: process.env.CAPACITOR_SERVER_URL.startsWith('http://'),
        }
        : undefined,
};

export default config;
