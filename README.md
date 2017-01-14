Bolt Installer
=================

**This is the official installer to start new projects based on the Bolt CMS
framework.**

Installing the installer
------------------------

This step is only needed the first time you use the installer:

### Linux and Mac OS X

```bash
$ sudo curl -LsS https://bolt.cm/installer -o /usr/local/bin/bolt
$ sudo chmod a+x /usr/local/bin/bolt
```

### Windows

```bash
c:\> php -r "file_put_contents('bolt', file_get_contents('https://bolt.cm/installer'));"
```

Move the downloaded `bolt` file to your projects directory and execute
it as follows:

```bash
c:\> php bolt
```

If you prefer to create a global `bolt` command, execute the following:

```bash
c:\> (echo @ECHO OFF & echo php "%~dp0bolt" %*) > bolt.bat
```

Then, move both files (`bolt` and `bolt.bat`) to any location included in your
execution path. Now you can run the `bolt` command anywhere on your system.

Using the installer
-------------------

**1. Start a new project with the latest stable Bolt version**

Execute the `new` command and provide the name of your project as the only
argument:

```bash
# Linux, Mac OS X
$ bolt new my_project

# Windows
c:\> php bolt new my_project
```

**2. Start a new project with the latest Bolt LTS (Long Term Support) version**

Execute the `new` command and provide the name of your project as the first
argument and `lts` as the second argument. The installer will automatically
select the most recent LTS (*Long Term Support*) version available:

```bash
# Linux, Mac OS X
$ bolt new my_project lts

# Windows
c:\> php bolt new my_project lts
```

**3. Start a new project based on a specific Bolt branch**

Execute the `new` command and provide the name of your project as the first
argument and the branch number as the second argument. The installer will
automatically select the most recent version available for the given branch:

```bash
# Linux, Mac OS X
$ bolt new my_project 3.4

# Windows
c:\> php bolt new my_project 3.4
```

**4. Start a new project based on a specific Bolt version**

Execute the `new` command and provide the name of your project as the first
argument and the exact Bolt version as the second argument:

```bash
# Linux, Mac OS X
$ bolt new my_project 3.4.2

# Windows
c:\> php bolt new my_project 3.4.2
```

**5. Install the Bolt demo application**

The Bolt Demo is a reference application developed using the official Bolt
Best Practices:

```bash
# Linux, Mac OS X
$ bolt demo

# Windows
c:\> php bolt demo
```

Updating the installer
----------------------

New versions of the Bolt Installer are released regularly. To update your
installer version, execute the following command:

```bash
# Linux, Mac OS X
$ bolt self-update

# Windows
c:\> php bolt self-update
```

> **NOTE**
>
> If your system requires the use of a proxy server to download contents, the
> installer tries to guess the best proxy settings from the `HTTP_PROXY` and
> `http_proxy` environment variables. Make sure any of them is set before
> executing the Bolt Installer.

Troubleshooting
---------------

### SSL and certificates issues on Windows systems

If you experience any error related with SSL or security certificates when using
the Bolt Installer on Windows systems:

1) Check that the OpenSSL extension is enabled in your `php.ini` configuration:

```ini
; make sure that the following line is uncommented
extension=php_openssl.dll
```

2) Check that the path to the file that contains the security certificates
exists and is defined in `php.ini`:

```ini
openssl.cafile=C:/path/to/cacert.pem
```

If you can't locate the `cacert.pem` file anywhere on your system, you can
safely download it from the official website of the cURL project:
http://curl.haxx.se/ca/cacert.pem

# Building PHAR

To build:

Install Box:

```bash
composer global require kherge/box
```

Run box:
```bash
box build
```
