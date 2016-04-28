<?php
/**
 * Copyright 2016 Pinterest, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Ensures Python package requirements are sorted and unique.
 */
final class PythonRequirementsLinter extends ArcanistLinter {

  const LINT_DUPLICATES = 1;
  const LINT_UNSORTED = 2;

  public function getInfoName() {
    return 'Python requirements.txt Linter';
  }

  public function getInfoDescription() {
    return pht('Ensures package requirements are sorted and unique.');
  }

  public function getInfoURI() {
    return 'https://pip.readthedocs.org/en/latest/user_guide/#requirements-files';
  }

  public function getLinterName() {
    return 'REQUIREMENTS-TXT';
  }

  public function getLinterConfigurationName() {
    return 'requirements-txt';
  }

  public function getLintSeverityMap() {
    return array(
      self::LINT_DUPLICATES => ArcanistLintSeverity::SEVERITY_ERROR,
      self::LINT_UNSORTED => ArcanistLintSeverity::SEVERITY_WARNING,
    );
  }

  public function getLintNameMap() {
    return array(
      self::LINT_DUPLICATES => pht('Duplicate package requirement'),
      self::LINT_UNSORTED => pht('Unsorted package requirement'),
    );
  }

  private function parseRequirement($line) {
    # PEP 508 (https://www.python.org/dev/peps/pep-0508/)
    $regex = "/^(?P<name>[[:alnum:]][[:alnum:]-_.]*)".
             "(?:\s*(?P<cmp>(~=|==|!=|<=|>=|<|>|===))\s*".
             "(?P<version>[[:alnum:]-_.*+!]+))?/";

    $matches = array();
    if (preg_match($regex, $line, $matches)) {
      return $matches;
    }

    return null;
  }

  private function lintDuplicates($lines) {
    $packages = array();

    foreach ($lines as $lineno => $line) {
      $req = $this->parseRequirement($line);
      if ($req === null) {
        continue;
      }

      $package = strtolower($req['name']);
      if (array_key_exists($package, $packages)) {
        $first = $packages[$package];
        $this->raiseLintAtLine(
          $lineno + 1,
          1,
          self::LINT_DUPLICATES,
          pht(
            'This line contains a duplicate package requirement for "%s". '.
            'The first reference appears on line %d: "%s"',
            $package, $first[0], $first[1]),
          $package);
      } else {
        $packages[$package] = array($lineno + 1, $line);
      }
    }
  }

  private function lintUnsorted($lines) {
    $last = null;

    foreach ($lines as $lineno => $line) {
      $req = $this->parseRequirement($line);
      if ($req === null) {
        continue;
      }

      $package = $req['name'];
      if (strnatcasecmp($package, $last) <= 0) {
        $this->raiseLintAtLine(
          $lineno + 1,
          1,
          self::LINT_UNSORTED,
          pht(
            "This line doesn't appear in sorted order. Please keep ".
            "package requirements ordered alphabetically."));
      }

      $last = $package;
    }
  }

  public function lintPath($path) {
    $lines = phutil_split_lines($this->getData($path), false);
    $lines = array_map('trim', $lines);

    if ($this->isMessageEnabled(self::LINT_DUPLICATES)) {
      $this->lintDuplicates($lines);
    }
    if ($this->isMessageEnabled(self::LINT_UNSORTED)) {
      $this->lintUnsorted($lines);
    }
  }
}
