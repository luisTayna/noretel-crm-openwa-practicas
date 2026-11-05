<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Core\Services\NotificationService;
use Modules\Core\Http\Requests\NotificationRequest;
use Modules\Core\Http\Requests\NotificationTemplateRequest;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * @var NotificationService
     */
    protected $notificationService;

    /**
     * NotificationController constructor.
     *
     * @param NotificationService $notificationService
     */
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Display a listing of the user's notifications.
     *
     * @return Renderable
     */
    public function index()
    {
        $result = $this->notificationService->getUserNotifications(Auth::user()->email);

        return view('core::notifications.index', [
            'notifications' => $result['notifications']
        ]);
    }

    /**
     * Display a listing of the user's unread notifications.
     *
     * @return Renderable
     */
    public function unread()
    {
        $result = $this->notificationService->getUserNotifications(Auth::user()->email);

        // Filtrar solo las notificaciones no leídas
        $unreadNotifications = $result['notifications']->filter(function($notification) {
            return !isset($notification->metadata['read']) || !$notification->metadata['read'];
        });

        return view('core::notifications.unread', [
            'notifications' => $unreadNotifications
        ]);
    }

    /**
     * Mark a notification as read.
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function markAsRead($id)
    {
        $result = $this->notificationService->markAsRead($id, Auth::id());

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', 'Notificación marcada como leída');
    }

    /**
     * Display a listing of notification templates.
     *
     * @return Renderable
     */
    public function templates()
    {
        // Obtener tipos de comunicación activos para filtrar
        $types = ['email', 'sms', 'system'];

        // Obtener plantillas según el tipo seleccionado
        $selectedType = request('type', 'all');

        if ($selectedType !== 'all') {
            $result = $this->notificationService->getTemplatesByType($selectedType);
            $templates = $result['templates'];
        } else {
            // Obtener todas las plantillas
            $templates = \Modules\Core\Entities\NotificationTemplate::paginate(15);
        }

        return view('core::notifications.templates.index', [
            'templates' => $templates,
            'types' => $types,
            'selectedType' => $selectedType
        ]);
    }

    /**
     * Show the form for creating a new notification template.
     *
     * @return Renderable
     */
    public function createTemplate()
    {
        $communicationTypes = ['email', 'sms', 'system'];
        $languages = ['es', 'en'];

        return view('core::notifications.templates.create', [
            'communicationTypes' => $communicationTypes,
            'languages' => $languages
        ]);
    }

    /**
     * Store a newly created notification template.
     *
     * @param NotificationTemplateRequest $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function storeTemplate(NotificationTemplateRequest $request)
    {
        $result = $this->notificationService->saveTemplate(
            $request->validated(),
            Auth::id(),
            $request->ip()
        );

        if (!$result['success']) {
            return back()->with('error', 'No se pudo guardar la plantilla')->withInput();
        }

        return redirect()->route('core.notifications.templates')
            ->with('success', 'Plantilla creada correctamente');
    }

    /**
     * Show the form for editing a notification template.
     *
     * @param int $id
     * @return Renderable
     */
    public function editTemplate($id)
    {
        $template = \Modules\Core\Entities\NotificationTemplate::findOrFail($id);
        $communicationTypes = ['email', 'sms', 'system'];
        $languages = ['es', 'en'];

        return view('core::notifications.templates.edit', [
            'template' => $template,
            'communicationTypes' => $communicationTypes,
            'languages' => $languages
        ]);
    }

    /**
     * Update the specified notification template.
     *
     * @param NotificationTemplateRequest $request
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateTemplate(NotificationTemplateRequest $request, $id)
    {
        $template = \Modules\Core\Entities\NotificationTemplate::findOrFail($id);

        $data = $request->validated();
        $data['id'] = $id;

        $result = $this->notificationService->saveTemplate(
            $data,
            Auth::id(),
            $request->ip()
        );

        if (!$result['success']) {
            return back()->with('error', 'No se pudo actualizar la plantilla')->withInput();
        }

        return redirect()->route('core.notifications.templates')
            ->with('success', 'Plantilla actualizada correctamente');
    }

    /**
     * Remove the specified notification template.
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroyTemplate($id)
    {
        $template = \Modules\Core\Entities\NotificationTemplate::findOrFail($id);

        // Registrar auditoría antes de eliminar
        \Modules\Core\Entities\AuditLog::register(
            Auth::id(),
            'template_deleted',
            'notifications',
            "Plantilla eliminada: {$template->name}",
            request()->ip(),
            $template->toArray(),
            null
        );

        $template->delete();

        return redirect()->route('core.notifications.templates')
            ->with('success', 'Plantilla eliminada correctamente');
    }

    /**
     * Send a notification.
     *
     * @param NotificationRequest $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function send(NotificationRequest $request)
    {
        $result = $this->notificationService->sendNotification(
            $request->template_name,
            $request->recipient,
            $request->data,
            $request->channel,
            $request->send_at ? \Carbon\Carbon::parse($request->send_at) : null
        );

        if (!$result['success']) {
            return back()->with('error', $result['message'])->withInput();
        }

        return back()->with('success', 'Notificación enviada correctamente');
    }

    /**
     * Process pending notifications (for manual trigger from UI)
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function processPending()
    {
        $result = $this->notificationService->processPendingNotifications();

        return back()->with('success', "Notificaciones procesadas: {$result['processed']} éxito, {$result['failed']} fallidas");
    }

    /**
     * Show the form to send a test notification.
     *
     * @return Renderable
     */
    public function testForm()
    {
        $templates = \Modules\Core\Entities\NotificationTemplate::where('active', true)
            ->orderBy('name')
            ->get();

        return view('core::notifications.test', [
            'templates' => $templates
        ]);
    }

    /**
     * Preview a notification.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function preview(Request $request)
    {
        $request->validate([
            'template_name' => 'required|string',
            'data' => 'required|array'
        ]);

        $result = $this->notificationService->previewNotification(
            $request->template_name,
            $request->data
        );

        if (!$result['success']) {
            return response()->json(['error' => $result['message']], 404);
        }

        return response()->json($result['preview']);
    }
}
