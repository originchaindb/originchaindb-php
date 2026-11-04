"""Install a built candidate into a temporary application. No registry uploads."""

import json
import os
from pathlib import Path
import subprocess
import tarfile
import tempfile
import zipfile

ROOT = Path(__file__).resolve().parents[1]
ARTIFACTS = ROOT / "artifacts"


def run(command, cwd, env=None):
    subprocess.run([str(value) for value in command], cwd=cwd, env=env, check=True)


def unpack(artifact, destination):
    if artifact.suffix == ".zip":
        with zipfile.ZipFile(artifact) as archive:
            for name in archive.namelist():
                if (
                    not (destination / name)
                    .resolve()
                    .is_relative_to(destination.resolve())
                ):
                    raise ValueError("Unsafe archive path")
            archive.extractall(destination)
    else:
        with tarfile.open(artifact) as archive:
            for item in archive.getmembers():
                if (
                    item.issym()
                    or item.islnk()
                    or (
                        not (destination / item.name)
                        .resolve()
                        .is_relative_to(destination.resolve())
                    )
                ):
                    raise ValueError("Unsafe archive entry")
            archive.extractall(destination, filter="data")


with tempfile.TemporaryDirectory(prefix="originchaindb-install-") as temporary:
    app = Path(temporary)
    unpack(next(ARTIFACTS.glob("*.zip")), app / "package")
    module = next((app / "package").rglob("composer.json")).parent
    (app / "composer.json").write_text(
        json.dumps(
            {
                "name": "originchain/install-check",
                "require": {"originchain/originchaindb": "1.0.0-rc.1"},
                "repositories": [
                    {
                        "type": "path",
                        "url": module.as_posix(),
                        "options": {
                            "symlink": False,
                            "versions": {"originchain/originchaindb": "1.0.0-rc.1"},
                        },
                    }
                ],
            }
        )
    )
    composer = (
        ["php", os.environ["COMPOSER_PHAR"]]
        if "COMPOSER_PHAR" in os.environ
        else ["composer"]
    )
    run([*composer, "install", "--no-interaction", "--no-dev"], app)
    (app / "check.php").write_text(
        "<?php require __DIR__.'/vendor/autoload.php'; $db=new OriginChainDB\\Client('https://example.invalid','test'); foreach(['writeBatches','pages','scan'] as $name){if(!method_exists($db->rows,$name)){throw new RuntimeException($name);}} $db->close();"
    )
    run(["php", "check.php"], app)
print("Fresh package application passed: " + "php")
