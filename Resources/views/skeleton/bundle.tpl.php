<?= "<?php\n" ?>

return [
<?php foreach ($parameters['prodBundles'] as $bundle): ?>
    <?= get_class($bundle) ?>::class => ['all' => true],
<?php endforeach; ?>
<?php foreach ($parameters['devBundles'] as $bundle): ?>
    <?= get_class($bundle) ?>::class => ['dev' => true, 'test' => true],
<?php endforeach; ?>
];
