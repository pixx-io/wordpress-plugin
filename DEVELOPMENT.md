# pixx.io Official WordPress Plugin - Development

## Prerequisites

The packages `@wordpress/scripts` and `adm-zip` are used for linting and packaging and should be installed using

```shell
npm install
```

When using VSCode, there is a workspace configuration and suggested extensions file included in the repository.

You should add `wordpress` to the array for `"intelephense.stubs"` in your user settings file and make sure to follow the [installation instructions vor phpsab](https://github.com/valeryan/vscode-phpsab) so that the `"phpsab.executablePathCBF"` and `"phpsab.executablePathCS"` settings are set to the correct paths.

## Linting

We use `@wordpress/scripts` for linting of JS and CSS by running

```shell
npm run lint
```

To trigger autoformat for PHP files, after following the installation instructions for phpsab (see above), you can press `Alt+Shift+F`.

Linting is currently not run automatically (neither PHP nor JS/CSS), but should be run from time to time to make sure we adhere to the WordPress coding standards.

## Bundling / Packaging / Distribution

There's currently no bundling process involved as we're using vanilla JavaScript (with the exception of jQuery.ajax() for simplicity, as it's ). However, there are some scripts involved for creating the distribution .zip file by running

```shell
npm run dist
```

This will automatically update the plugin header in the main plugin file to reflect the version from `package.json` and bundle all plugin files into a single .zip file as `dist/pixxio-vX.X.X.zip`, ready for distribution and installation in a WordPress instance.
