#!/bin/bash

update () {
  git checkout .
  git pull --rebase
}

installGitTermux () {
  pkg install git
  update
}

if [ type gits &> /dev/null ]; then
  update
else
  echo "Git belum terinstall"
  read -e -p "Install git? [y/N]: " gitPrompt
  if [ $gitPrompt == "y" ]; then
    read -e -p "Memakai termux? [y/N]: " termuxPrompt
    if [ $termuxPrompt == "y" ]; then
      installGitTermux
    else
      echo "Silahkan install sesuai OS / Terminal yang digunakan"
    fi
  else
    echo "Git diperlukan untuk melakukan operasi ini"
    exit 1
  fi
fi