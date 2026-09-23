<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\BookingRepository;
use App\Repositories\MessageRepository;

final class MessageController extends Controller
{
    private const PER_PAGE = 25;

    public function __construct(
        private MessageRepository $messages = new MessageRepository(),
        private BookingRepository $bookings = new BookingRepository()
    ) {
    }

    /** GET /admin/messages */
    public function index(Request $request): Response
    {
        $page = $this->page($request);
        $status = $request->string('status');
        $result = $this->messages->paginate($page, self::PER_PAGE, $status);

        return $this->view('admin.messages.index', [
            'title'      => 'Messages',
            'messages'   => $result['rows'],
            'pagination' => $this->paginationMeta($result['total'], $page, self::PER_PAGE),
            'status'     => $status,
            'unread'     => $this->messages->countUnread(),
        ]);
    }

    /** GET /admin/messages/{id} */
    public function show(Request $request, array $parameters): Response
    {
        $id = (int) $parameters['id'];
        $message = $this->orFail($this->messages->find($id), 'Message introuvable.');

        $this->messages->markRead($id);

        return $this->view('admin.messages.show', [
            'title'   => 'Message de ' . (string) $message['name'],
            'message' => $message,
        ]);
    }

    /** POST /admin/messages/{id}/status */
    public function setStatus(Request $request, array $parameters): Response
    {
        $id = (int) $parameters['id'];
        $this->orFail($this->messages->find($id), 'Message introuvable.');

        $status = $request->string('status');

        if (!in_array($status, ['new', 'read', 'archived'], true)) {
            $this->abort(422, 'Statut inconnu.');
        }

        $this->messages->setStatus($id, $status);
        $this->flashSuccess('Message mis à jour.');

        return $this->back($request, 'admin/messages');
    }

    /** DELETE /admin/messages/{id} */
    public function destroy(Request $request, array $parameters): Response
    {
        $id = (int) $parameters['id'];
        $this->orFail($this->messages->find($id), 'Message introuvable.');

        $this->messages->delete($id);
        $this->flashSuccess('Message supprimé.');

        return $this->redirect('admin/messages');
    }

    /** GET /admin/bookings */
    public function bookings(Request $request): Response
    {
        $page = $this->page($request);
        $status = $request->string('status');
        $result = $this->bookings->paginate($page, self::PER_PAGE, $status);

        return $this->view('admin.messages.bookings', [
            'title'      => 'Demandes de réservation',
            'bookings'   => $result['rows'],
            'pagination' => $this->paginationMeta($result['total'], $page, self::PER_PAGE),
            'status'     => $status,
            'pending'    => $this->bookings->countPending(),
        ]);
    }

    /** POST /admin/bookings/{id}/status */
    public function setBookingStatus(Request $request, array $parameters): Response
    {
        $id = (int) $parameters['id'];
        $this->orFail($this->bookings->find($id), 'Demande introuvable.');

        $status = $request->string('status');

        if (!in_array($status, ['pending', 'confirmed', 'declined', 'archived'], true)) {
            $this->abort(422, 'Statut inconnu.');
        }

        $this->bookings->setStatus($id, $status);
        $this->flashSuccess('Demande mise à jour.');

        return $this->back($request, 'admin/bookings');
    }
}
