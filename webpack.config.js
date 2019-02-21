var Encore = require('@symfony/webpack-encore');

Encore
  .setOutputPath('./Resources/public/')
  .setPublicPath('./')
  .setManifestKeyPrefix('bundles/harmonyadmin')

  .cleanupOutputBeforeBuild()
  .enableSassLoader()
  .enableSourceMaps(false)
  .enableVersioning(false)
  .disableSingleRuntimeChunk()
  .autoProvidejQuery()

  // needed to avoid this bug: https://github.com/symfony/webpack-encore/issues/436
  .configureCssLoader(options => { options.minimize = false; })

  .addEntry('main', './assets/js/main.js')
;

module.exports = Encore.getWebpackConfig();