#!/bin/bash
# test phar creation / modification
set -euo pipefail
IFS=$'\n\t'

# TEST SETUP
. tests-framework.sh # include test framework

#
#               TEST PLAN
#
# [ 1] require phpunit 8 (changes project files)
# [ 2] patch for phpunit 8 (changes project files)
# [ 3] run phpunit tests
# [ 4] reset phpunit to project baseline, checkout test-suite
#

PROJECT_DIR=../..

case ${1-0} in
  0 ) echo "# 0: ${0} run"
      run_test "${0}" 1 2 3
      exit
      ;;
  1 ) echo "# 1: require phpunit 8"
      cd "${PROJECT_DIR}"
      "${PHP_BIN-php}" -f "$(composer which 2>/dev/null)" -- -n require --dev phpunit/phpunit ^8 --update-with-dependencies --no-suggest
      exit
      ;;
  2 ) echo "# 2: patch for phpunit 8"
      cd "${PROJECT_DIR}"
      sed -i \
        -e '/protected function create.*Mock/ s/)$/): MockObject/' \
        -e '/public static function assert.*/ s/)$/): void/' \
        -e '/public function expect.*/ s/)$/): void/' \
        tests/TestCase.php
      find tests -type f -name '*Test*.php' \
        -exec sed -i -e '/ setUp(/ s/)$/): void/' -e '/ tearDown(/ s/)$/): void/' {} \;
      exit
      ;;
  3 ) echo "# 3: run phpunit tests"
      cd "${PROJECT_DIR}"
      "${PHP_BIN-php}" -f "$(composer which 2>/dev/null)" -- phpunit-test
      exit
      ;;
  4 ) echo "# 4: reset phpunit to project baseline, checkout test-suite"
      cd "${PROJECT_DIR}"
      git checkout -- composer.* tests/TestCase.php tests/unit
      "${PHP_BIN-php}" -f "$(composer which 2>/dev/null)" -- install
      exit
      ;;
  * ) >&2 echo "unknown step ${1}"
      exit 1
      ;;
esac
