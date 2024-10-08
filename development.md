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

Linting is only run automatically when using `npm run dist`, but should be run from time to time to make sure we adhere to the WordPress coding standards.

## Release a new version

1. Update `CHANGELOG.md`
1. Update `readme.txt`
2. Update the version in `package.json` and run `npm i`
3. Update the version in `pixx-io.php`
4. Merge the changes in the `master` branch
5. Create a new tag on the `master` branch: `git tag 2.0.1 && git push origin --tags`
6. A Github workflow starts which copies the new files to the wordpress plugin SVN

## Update readme.txt or (marketing) assets without a new release

- Update the files in `/assets` or the `readme.txt`.
- Create a new branch
- Merge the branch into the `master` branch
- A Github workflow starts and updates the Assets and readme.txt in the plugin directory

## Creating a ZIP-File - OUTDATED

Use these script only for preview purposes.

### Bundling / Packaging / Distribution

There's currently no bundling process involved as we're using vanilla JavaScript (with the exception of jQuery.ajax() for simplicity). However, there are some scripts involved for creating the distribution .zip file by running

```shell
npm run pack
```

or to run linting first and then pack:

```shell
npm run dist
```

This will automatically update the plugin header in the main plugin file to reflect the version from `package.json` and bundle all plugin files into a single .zip file as `dist/pixxio-vX.X.X.zip`, ready for distribution and installation in a WordPress instance.

You can use the npm scripts `pack-major`, `pack-minor`, `pack-patch` and `pack-major`, `pack-minor`, `pack-patch` to automatically bump the version using the corresponding `npm version` call, with or without prior linting and finally packing, all in one go.
