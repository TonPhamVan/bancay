<?php

// Exment registers its own authenticated admin/login routes.
// Domain administration screens are deferred to Phase 2; do not call Admin::routes()
// here, which would duplicate/override Exment's authentication controllers.
