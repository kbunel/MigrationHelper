<?php

namespace MigrationHelperSF4\Model;

class FileAnalyzed
{
    public $kind;
    public $originPath;
    public $newPath;
    public $originNamespace;
    public $newNamespace;
    public $originBundleNamePath;
    public $newBundleNamePath;
    public $isDeleted = false;
}