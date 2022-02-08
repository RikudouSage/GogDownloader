# gog-downloader

PHP based tool to download the games you have bought on GOG to your hard drive.

Requires `php 8.1+` (with simplexml and json) or `docker`.

By default, the games are saved in the directory you run the commands from, you can also specify your own.

You can run `gog-downloader help` or `gog-downloader help [subcommand]` for more info.

## Features

- Choose which games to download based on language, os or search parameters
- Checks that the downloaded files are valid
- Resume partially downloaded files instead of downloading them whole again
- Can be easily put into cron jobs

## Usage

First you need to log in, you can use one of two ways - login via code or username and password:

- `gog-downloader login [username]`
- `gog-downloader code-login`

After logging in your credentials are stored and will be reused. This app logs in the same way as GOG Galaxy does.

Afterwards you need to update your local database:

- `gog-downloader update-database` (or just `gog-downloader update`)

Afterwards you can start downloading:

- `gog-downloader download [target-directory]`

### Logging in via code

This method is preferred as it works even if you trigger the GOG's recaptcha challenge.

1. Run `gog-downloader code-login`
2. A prompt looking like this will open:
```text
Visit https://auth.gog.com/auth?client_id=46899977096215655&redirect_uri=https%3A%2F%2Fembed.gog.com%2Fon_login_success%3Forigin%3Dclient&response_type=code&layout=client2 and log in. 
After you're logged in you should be redirected to a blank page, copy the address of the page and paste it here (the prompt is invisible, you won't see that you pasted anything).      

 Code or web address:
 >
```
3. Open the displayed link in any browser (doesn't have to be on the same machine as this app is running)
4. Log in to GOG as you normally would
5. After being redirected to a blank white page, copy the web address from your browser
6. Paste it into the prompt (which is invisible, so you won't see that you have pasted anything)
   (the address will look something like `https://embed.gog.com/on_login_success?origin=client&code=ijMoGxXDV86TAPNYi7nLSMSiw-yYlbl1NKyO1Lzto9BTo-t83P4GfZa2ZJeAYYtx3UP0YfddCd5HFiTiuds2pMEx_2iaPam3KpdvuX2IxlmOy5Gu6dffPM4TfXCzDEOsd7VwocTwFGekO_hTU9mHLhJdQ80OeIDcrrnHY_KhchWLiitYdkftyHochx7GsJDII`)
7. Press enter
8. If you see `[OK] Logged in successfully.` you're done
   1. If you see `Failed to log in using the code, please try again.` you probably did something wrong, or you waited too long
      before pasting the address, you can try again.

### Logging in via username and password

As mentioned before, this method might fail due to recaptcha or two-factor auth.

1. Run `gog-downloader login` or `gog-downloader login [your-email]` (where you replace `[your-email]` with your GOG email)
2. The app will ask for password, type it or paste it there (the prompt is hidden, you won't see any typing)
3. If the credentials were correct and there was no recaptcha or two-factor auth request you will see `[OK] Successfully logged in`
   1. If you see an error, you can try again or log in using the `code-login` command

### Updating your local database

Before downloading you should update your local cache of all downloadable files.

This is done by command `update-database` or simply `update`:

`gog-downloader update`

You can also filter games by language, operating system or by searching a game. You can also update only new games
or games that have updates.

Examples:

- `gog-downloader update --os windows` - download metadata only for Windows games
- `gog-downloader update --language en` - download metadata only for games that support English
- `gog-downloader update --search witcher` - downloads only games that match the search string, in this case "witcher"
- `gog-downloader update --updated-only` - only new and updates games' metadata will be downloaded
- `gog-downloader update --new-only` - only new games' metadata will be downloaded

All of these can be combined, so if you for example want only games that work on Linux, have Czech localization and are
new, you would run `gog-downloader update --os linux --language cz --new-only`.

Newly downloaded games are always added to the local database, if you want to delete the database before
downloading it anew, add the `--clear` argument to your command.

Note that this command always downloads metadata for **all** language and os version, it just filters games that support
your given combination of os/language/search.

> Tip: If you want to search for a game that contains space or other special characters, enclose it in quotes so it
> looks like this: `gog-downloader update --search "assassins of kings"`
 
> Tip: To list all languages available, run `gog-downloader languages`

> Tip: Most of the arguments also have a short form, for example instead of `--os` you can use `-o`, instead of
> `--language` you can use `-l`, for `--search` you can use `-s`, `-u` for `--updated-only`, `-new` for `--new-only`
> etc. For list of arguments run `gog-downloader help update`.

### Downloading games

For downloading you use the `download` command: `gog-downloader download`.

You can filter by language and operating system. You can also fall back to English if the game doesn't support the
language you specified. You can also specify the target directory

- `gog-downloader download --os windows` - download only Windows files
- `gog-downloader download --language en` - download only English files
- `gog-downloader download --language cz --language-fallback-english` - download only Czech files, if the game doesn't
  support Czech, English files will be downloaded instead
- `gog-downloader download TargetDownloadsDirectory` - downloads the files into `TargetDownloadsDirectory` directory

The arguments can of course be combined: `gog-downloader download --os windows --language en TargetDownloadsDirectory`.

As mentioned before, the `update` command downloads metadata for all languages and os versions, so if you run the
commands below, all games supporting Czech or English would be downloaded.

- `gog-downloader update`
- `gog-downloader download --language cz --language-fallback-english`

To download only specific language/os you should run both `update` and `download` with the same parameters 
(or without the `--language-fallback-english`).

Some games on GOG support a language in-game but don't offer separate download for the given language - the language
is part of the default (English) installation. For such cases there's the `--language-fallback-english` flag
which allows you to download the English version if the specified language cannot be found.

So to download only games that support Czech either in-game or as a separate download, you would run:

- `gog-downloader update --clear --language cz` (the `--clear` is there to delete any metadata from previous `update` runs)
- `gog-downloader download --language cz --language-fallback-english`

## Download

If you want to use the docker version, read below.

If your local system has php, you can download the [latest release](https://github.com/RikudouSage/GogDownloader/releases/latest)
and run it locally, either by using `php gog-downloader` or by running `./gog-downloader`.

> If you run into permission problems, make the `gog-downloader` file executable by running `chmod +x gog-downloader`

You can also download the latest development version from [here](https://nightly.link/RikudouSage/GogDownloader/workflows/build-latest.yaml/main/gog-downloader.zip).
This version will have new features sooner than standard release, but you may also encounter new bugs.

### Docker

You can use the docker image `rikudousage/gog-downloader`.

It doesn't offer any configuration, but you can mount two volumes, one for configuration (under `/Configs`)
and the other for downloads (under `/Downloads`).

You can use specific versions as tags or `latest` (the newest stable version) or `dev` (the newest unstable version).

Example 1:

- `docker run --rm -it --init -v $(pwd)/Configs:/Configs -v $(pwd)/Downloads:/Downloads rikudousage/gog-downloader:latest`

Explanation:

- This creates an interactive container (needed for logging in and other stuff) that gets removed immediately after use.
- The config directory is mounted to a directory `Configs` created in the current directory.
- The downloads directory is mounted to a directory `Downloads` created in the current directory.
- The latest stable version is used.
- The files and configs are created under the `root` user which may pose some permission issues
  - These may be resolved by running `sudo chown -R $(id -u):$(id -g) Configs Downloads` after every download session
  - To download directly under your current user, see the next example

Example 2:

- `mkdir -p Configs Downloads; docker run --rm -it --init -v /etc/passwd:/etc/passwd:ro -v $(pwd)/Configs:/Configs -v $(pwd)/Downloads:/Downloads --user $(id -u) rikudousage/gog-downloader:latest`

Explanation:

- This first creates the directories `Configs` and `Downloads` under your current user
- It mounts the `/etc/passwd` (which contrary to its name doesn't contain passwords) file to the container in read-only mode
  so the container knows the list of your local users
- It sets the user running inside container as your current user
- Everything else is the same as in the previous example
- No more permission issues with downloaded files and configs

#### Aliasing

Typing such commands all the time is tiring, that's why I recommend creating an alias:

- `alias gog-downloader='docker run --rm -it --init -v $(pwd)/Configs:/Configs -v $(pwd)/Downloads:/Downloads rikudousage/gog-downloader:latest'`
  - this aliases the command `gog-downloader` to the first example
- `alias gog-downloader='mkdir -p Configs Downloads; docker run --rm -it --init -v /etc/passwd:/etc/passwd:ro -v $(pwd)/Configs:/Configs -v $(pwd)/Downloads:/Downloads --user $(id -u) rikudousage/gog-downloader:latest'`
  - this aliases the command `gog-downloader` to the second example

Afterwards you can use the `gog-downloader` command the same as if you installed it locally,
for example `gog-downloader code-login`.

If you add the alias line into your `~/.bashrc` or `~/.zshrc` (depending on your shell), the `gog-downloader` command
will be available every time you start a command line shell.

## Commands

List of all commands that this app supports:

### code-login

```
Description:
  Login using a code (for example when two-factor auth or recaptcha is required)

Usage:
  code-login [<code>]

Arguments:
  code                  The login code or url. Treat the code as you would treat a password. Providing the code as an argument is not recommended.

Options:
  -h, --help            Display help for the given command. When no command is given display help for the list command
  -q, --quiet           Do not output any message
  -V, --version         Display this application version
      --ansi|--no-ansi  Force (or disable --no-ansi) ANSI output
  -n, --no-interaction  Do not ask any interactive question
  -v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
```

### login

```
Description:
  Login via username and password. May fail due to recaptcha, prefer code login.

Usage:
  login [options] [--] [<username>]

Arguments:
  username                 Username to log in as, if empty will be asked interactively

Options:
      --password=PASSWORD  Your password. It's recommended to let the app ask for your password interactively instead of specifying it here.
  -h, --help               Display help for the given command. When no command is given display help for the list command
  -q, --quiet              Do not output any message
  -V, --version            Display this application version
      --ansi|--no-ansi     Force (or disable --no-ansi) ANSI output
  -n, --no-interaction     Do not ask any interactive question
  -v|vv|vvv, --verbose     Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
```

### languages

```
Description:
  Lists all supported languages

Usage:
  languages

Options:
  -h, --help            Display help for the given command. When no command is given display help for the list command
  -q, --quiet           Do not output any message
  -V, --version         Display this application version
      --ansi|--no-ansi  Force (or disable --no-ansi) ANSI output
  -n, --no-interaction  Do not ask any interactive question
  -v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
```

### update-database (or update)

```
Description:
  Updates the games/files database.

Usage:
  update-database [options]
  update

Options:
  -u, --updated-only       Download information only about updated games
  -c, --clear              Clear local database before updating it
  -s, --search=SEARCH      Update only games that match the given search
  -o, --os=OS              Filter by OS, allowed values are: windows, mac, linux
  -l, --language=LANGUAGE  Filter by language, for list of languages run "languages"
  -h, --help               Display help for the given command. When no command is given display help for the list command
  -q, --quiet              Do not output any message
  -V, --version            Display this application version
      --ansi|--no-ansi     Force (or disable --no-ansi) ANSI output
  -n, --no-interaction     Do not ask any interactive question
  -new, --new-only         Download information only about new games
  -v|vv|vvv, --verbose     Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
```

### download

```
Description:
  Downloads all files from the local database (see update command). Can resume downloads unless --no-verify is specified.

Usage:
  download [options] [--] [<directory>]

Arguments:
  directory                        The target directory, defaults to current dir. [default: "/Downloads"]

Options:
      --no-verify                  Set this flag to disable verification of file content before downloading. Disables resuming of downloads.
  -o, --os=OS                      Download only games for specified operating system, allowed values: windows, mac, linux
  -l, --language=LANGUAGE          Download only games for specified language. See command "languages" for list of them.
      --language-fallback-english  Download english versions of games when the specified language is not found.
  -h, --help                       Display help for the given command. When no command is given display help for the list command
  -q, --quiet                      Do not output any message
  -V, --version                    Display this application version
      --ansi|--no-ansi             Force (or disable --no-ansi) ANSI output
  -n, --no-interaction             Do not ask any interactive question
  -v|vv|vvv, --verbose             Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
```
