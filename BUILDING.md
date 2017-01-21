
# Building PHAR

To build:

## Installing Box PHAR builder

```bash
composer global require kherge/box
```

**NOTE:** Do not install `kherge/box` in the project, this package **must** be
installed `global`, else it will be included in the PHAR. 

## Building with Box

```bash
box build -v
```

## Deploying PHAR

Copy to web root directory, and update the `installer.version` file in the
server web root.
