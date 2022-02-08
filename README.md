Download the latest version [here](https://nightly.link/RikudouSage/GogDownloader/workflows/build-latest.yaml/main/gog-downloader.zip).

`alias gog-downloader='mkdir -p Configs Downloads; docker run --rm -it --init -v /etc/passwd:/etc/passwd:ro -v $(pwd)/Configs:/Configs -v $(pwd)/Downloads:/Downloads --user $(id -u) rikudousage/gog-downloader:latest'`