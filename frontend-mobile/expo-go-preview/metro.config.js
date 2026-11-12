const path = require('path');
const { getDefaultConfig } = require('expo/metro-config');

const previewRoot = __dirname;
const sourceRoot = path.resolve(previewRoot, '..');
const config = getDefaultConfig(previewRoot);

require('dotenv').config({ path: path.join(sourceRoot, '.env') });

config.watchFolders = [sourceRoot];
config.resolver.nodeModulesPaths = [
  path.join(previewRoot, 'node_modules'),
  path.join(sourceRoot, 'node_modules'),
];
config.resolver.disableHierarchicalLookup = true;
config.transformer.getTransformOptions = async () => ({
  transform: {
    experimentalImportSupport: false,
    inlineRequires: true,
  },
});

module.exports = config;
