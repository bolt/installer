# Building the `bolt.phar` PHAR

To build:

## Installing Box PHAR builder

```bash
composer global require kherge/box
```

To use box by just running `box`, you will need to add the installation
directory to your `PATH` in your `~/.bash_profile` (or `~/.zshrc`) like this:

```
export PATH=~/.composer/vendor/bin:$PATH
```

**NOTE:** Do not install `kherge/box` in the project, this package **must** be
installed `global`, else it will be included in the PHAR.

## Building with Box

Run the following in project folder (with the `box.json` file):

```bash
box build -v
```

For testing in a browser, copy the the generated `bolt.phar` a web-accessible
directory and rename it to `install.php`.

## Deploying PHAR

Copy the generated to web root directory, and update the `installer.version`
file in the server web root.
