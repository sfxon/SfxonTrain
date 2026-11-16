import { createAppConfig } from '@nextcloud/vite-config'
import { join, resolve } from 'path'

export default createAppConfig(
    {
        adminTrain: resolve(join('src', 'views', 'AdminTrain', 'index.ts')),
        adminTrainContent: resolve(join('src', 'views', 'AdminTrainContent', 'index.ts')),
        adminTrainEdit: resolve(join('src', 'views', 'AdminTrainEdit', 'index.ts')),
        adminTrainParticipants: resolve(join('src', 'views', 'AdminTrainParticipants', 'index.ts')),
        dashboard: resolve(join('src', 'views', 'Dashboard', 'index.ts')),
        player: resolve(join('src', 'views', 'Player', 'index.ts')),
    },
    {
        createEmptyCSSEntryPoints: true,
        extractLicenseInformation: true,
        thirdPartyLicense: false,
        config: {
            resolve: {
                alias: {
                    '@': resolve('src'),
                },
            },
        },
    },
)
