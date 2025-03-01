{ pkgs ? import <nixpkgs> {} }:
pkgs.mkShell {
    nativeBuildInputs = with pkgs.buildPackages;
    let
        unstable = import (builtins.fetchTarball https://github.com/nixos/nixpkgs/tarball/master) {};
        php84 = unstable.php84.buildEnv {
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
        php84
        php84.packages.composer
        php84.extensions.redis
        php84.extensions.xsl
        php84.extensions.mbstring
        symfony-cli
        git
        nodejs_20
        nodePackages.serverless
    ];
}
