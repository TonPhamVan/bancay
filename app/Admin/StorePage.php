<?php

namespace App\Admin;

use Exceedone\Exment\Services\Plugin\PluginPageBase;

/** Exment performs authentication, CSRF, native shell and plugin routing. */
abstract class StorePage extends PluginPageBase
{
    protected const MODULE = '';

    public function index()
    {
        return app(StoreController::class)->index(static::MODULE);
    }

    public function listing($entity)
    {
        return app(StoreController::class)->listing(static::MODULE, $entity);
    }

    public function create($entity)
    {
        return app(StoreController::class)->form(static::MODULE, $entity);
    }

    public function show($entity, $id)
    {
        return app(StoreController::class)->show(static::MODULE, $entity, $id);
    }

    public function edit($entity, $id)
    {
        return app(StoreController::class)->form(static::MODULE, $entity, $id);
    }

    public function store($entity)
    {
        return app(StoreController::class)->save(static::MODULE, $entity);
    }

    public function update($entity, $id)
    {
        return app(StoreController::class)->save(static::MODULE, $entity, $id);
    }

    public function destroy($entity, $id)
    {
        return app(StoreController::class)->destroy(static::MODULE, $entity, $id);
    }

    public function command($entity, $id, $command)
    {
        return app(StoreController::class)->command(static::MODULE, $entity, $id, $command);
    }

    public function export($entity)
    {
        return app(StoreController::class)->export(static::MODULE, $entity);
    }
}
