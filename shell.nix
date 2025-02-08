{ pkgs ? import <nixpkgs> {} }:
pkgs.mkShell {
    nativeBuildInputs = with pkgs.buildPackages;
    let
        unstable = import (builtins.fetchTarball https://github.com/nixos/nixpkgs/tarball/master) {};
        php83 = unstable.php83.buildEnv {
            extensions = ({ enabled, all }: enabled ++ (with all; [
                ctype
                iconv
                intl
                mbstring
                pdo
                redis
                xdebug
                xsl
            ]));
            extraConfig = ''
                memory_limit=8G
                xdebug.mode=debug
            '';
        };
     in
     [
        php83
        php83.packages.composer
        php83.extensions.redis
        php83.extensions.xsl
        php83.extensions.mbstring
        symfony-cli
        git
        nodejs_20
        nodePackages.serverless
    ];
}
