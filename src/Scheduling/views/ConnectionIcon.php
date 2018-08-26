<?php
$scheduling = \Wpae\Scheduling\Scheduling::create();
?>
<span class="wpai-no-license" <?php if ($scheduling->checkLicense()) { ?> style="display: none;" <?php } ?> >

    <a href="#" style="z-index: 1000; position: absolute; top: 1px; left: 155px; padding: 0;" class="help_scheduling tipsy"
       title="Automatic Scheduling is a paid service from Soflyy. Click for more info.">
        <img style="width: 16px; "
             src="<?php echo PMXE_ROOT_URL; ?>/static/img/s-question.png"/>
    </a>
</span>


<span class="wpai-license" <?php if (!$scheduling->checkLicense()) { ?> style="display: none;" <?php } ?> >
    <?php if ( $scheduling->checkConnection() ) {
        ?>
        <a href="#" class="help_scheduling" title="Connection to WP All Export servers is stable and confirmed"
              style="z-index: 1000; position: absolute; top: 1px; left: 155px; padding: 0; width: 16px;">
        <img src="<?php echo PMXE_ROOT_URL; ?>/static/img/s-check.png" style="width: 16px;"/>
    </a>
        <?php
    } else  { ?>
        <img src="<?php echo PMXE_ROOT_URL; ?>/static/img/s-exclamation.png" style="width: 16px;"/>

        <?php
    }
    ?>
</span>
