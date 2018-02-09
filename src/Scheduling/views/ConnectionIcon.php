<?php
$scheduling = \Wpae\Scheduling\Scheduling::create();
?>
<?php if ($scheduling->checkConnection() && $scheduling->checkLicense()) {
    ?>
    <span class="wpallexport-help" title="Connection to WP All Export servers is stable and confirmed" style="background-image: none; width: 20px; height: 20px;;">
        <img src="<?php echo PMXE_ROOT_URL; ?>/static/img/s-check.png" style="width: 16px;" />
    </span>
    <?php
} else if(!$scheduling->checkConnection() && $scheduling->checkLicense() ) { ?>
    <img src="<?php echo PMXE_ROOT_URL; ?>/static/img/s-exclamation.png" style="width: 16px;" />
<?php } else { ?>
    <a href="#" class="help_scheduling">
        <img style="width: 16px; top: 2px; position: absolute;" src="<?php echo PMXE_ROOT_URL; ?>/static/img/s-question.png" />
    </a>
<?php } ?>