<?php

namespace App\Observers;

use App\Models\AiArticle;
use App\Models\AiImage;
use App\Models\Publication;
use App\Models\Scheduler;
use App\Models\SystemLog;
use App\Models\WordPressSite;
use App\Services\SystemLogService;
use Illuminate\Database\Eloquent\Model;

class SystemActivityObserver
{
    public function __construct(
        private readonly SystemLogService $logs,
    ) {}

    public function created(Model $model): void
    {
        $this->record($model, true);
    }

    public function updated(Model $model): void
    {
        $this->record($model, false);
    }

    private function record(Model $model, bool $created): void
    {
        if ($model instanceof Publication) {
            if ($created || $model->wasChanged(['status', 'error_message', 'remote_url', 'remote_post_id', 'remote_post_key'])) {
                $this->logs->recordPublication($model);
            }

            return;
        }

        if (! $created && ! $model->wasChanged(['status', 'last_error', 'generation_error', 'connection_error'])) {
            return;
        }

        if ($model instanceof Scheduler && $model->status === Scheduler::STATUS_FAILED) {
            $this->logs->recordError(
                SystemLog::EVENT_TASK_FAILED,
                'Programador',
                $model->last_error ?: 'El proceso terminó con error.',
                $model,
                ['task' => $model->name, 'type' => $model->type],
                $model->user_id,
            );

            return;
        }

        if ($model instanceof AiArticle && $model->status === AiArticle::STATUS_FAILED) {
            $this->logs->recordError(
                SystemLog::EVENT_ARTICLE_FAILED,
                'Artículos IA',
                $model->generation_error ?: 'No fue posible generar el artículo.',
                $model,
                ['article' => $model->title],
                $model->user_id,
            );

            return;
        }

        if ($model instanceof AiImage && $model->status === AiImage::STATUS_FAILED) {
            $model->loadMissing('article:id,user_id,title');
            $this->logs->recordError(
                SystemLog::EVENT_IMAGE_FAILED,
                'Imágenes IA',
                $model->generation_error ?: 'No fue posible generar la imagen.',
                $model,
                ['article' => $model->article?->title],
                $model->article?->user_id,
            );

            return;
        }

        if ($model instanceof WordPressSite && $model->status === WordPressSite::STATUS_ERROR) {
            $this->logs->recordError(
                SystemLog::EVENT_CONNECTION_FAILED,
                'Perfiles',
                $model->connection_error ?: 'Falló la conexión con el perfil de publicación.',
                $model,
                ['profile' => $model->name],
                $model->user_id,
            );
        }
    }
}
