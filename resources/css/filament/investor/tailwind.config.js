import preset from '../../../../vendor/filament/filament/tailwind.config.preset'

export default {
    presets: [preset],
    content: [
        './app/Filament/Investor/**/*.php',
        './resources/views/filament/investor/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
    ],
}
