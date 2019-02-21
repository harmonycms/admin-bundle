// webpack.config.js
let Encore = require('@symfony/webpack-encore');

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

  // Images
  .copyFiles({
    from: './assets/images',
    // optional target path, relative to the output dir
    to  : 'images/[path][name].[ext]'
  })

  // needed to avoid this bug: https://github.com/symfony/webpack-encore/issues/436
  .configureCssLoader(options => { options.minimize = false; })

  .addEntry('main', './assets/js/main.js')
;

module.exports = Encore.getWebpackConfig();