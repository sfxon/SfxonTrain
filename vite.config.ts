import { createAppConfig } from '@nextcloud/vite-config'
import { join, resolve } from 'path'

export default createAppConfig(
    {
        main: resolve(join('src', 'main.js')),
        player: resolve(join('src', 'player.ts')),
    },
    {
        createEmptyCSSEntryPoints: true,
        extractLicenseInformation: true,
        thirdPartyLicense: false,
    },
)
