<?php

namespace App\Http\Controllers;

use App\DTOs\CreateUserDTO;
use App\Http\Middleware\LogMiddleware;
use App\Models\User;
use App\Services\UserService;
use Core\Attributes\ApiController;
use Core\Attributes\Body;
use Core\Attributes\Get;
use Core\Attributes\Headers;
use Core\Attributes\Middleware;
use Core\Attributes\Param;
use Core\Attributes\Post;
use Core\Attributes\Query;
use Core\Attributes\Request as ReqAttr;
use Core\Attributes\UploadedFile;
use Core\Attributes\UploadedFiles;
use Core\Http\Request;
use Core\Http\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile as File;

#[ApiController('/users')]
#[Middleware([LogMiddleware::class])]
class UsersController
{
    #[Get('')] 
    public function index(): Response
    {
        return Response::json([
            'message' => 'List of users',
            'users' => User::all()->toArray()
        ]);
    }

    #[Get('/search')]
    public function search(
        #[Query('q')] ?string $query = '',
        #[Query('limit')] ?int $limit = 10,
        #[Query('page')] ?int $page = 1
    ): Response {
        // Query parameters with type conversion
        return Response::json([
            'message' => 'Search users',
            'query' => $query,
            'limit' => $limit,
            'page' => $page,
            'results' => []
        ]);
    }

    #[Get('/debug')]
    public function debug(#[ReqAttr] Request $request): Response
    {
        // Full request object access
        return Response::json([
            'headers' => $request->headers->all(),
            'query' => $request->query->all(),
            'cookies' => $request->cookies->all(),
            'server' => [
                'method' => $request->getMethod(),
                'uri' => $request->getUri(),
                'protocol' => $request->getProtocolVersion(),
                'host' => $request->getHost(),
                'port' => $request->getPort(),
                'scheme' => $request->getScheme()
            ],
            'client' => [
                'ip' => $request->getClientIp(),
                'ips' => $request->getClientIps(),
                'user_agent' => $request->headers->get('User-Agent')
            ]
        ]);
    }

    #[Get('/{id}')]
    public function show(#[Param('id')] int $id): Response
    {
        // Manual service instantiation for now
        // MOVED TO END: Wildcard routes must come after specific routes!
        $userService = new UserService();
        $response = $userService->getUserById($id);
        
        return Response::json($response->toArray(), $response->status_code);
    }

    #[Post('/')]
    public function create(#[Body] CreateUserDTO $dto): Response 
    {
        $userService = new UserService();
        $response = $userService->createUser($dto);
        
        return Response::json($response->toArray(), $response->status_code);
    }

    #[Post('/upload')]
    public function upload(
        #[ReqAttr] Request $request,
        #[Headers('x-api-version')] ?string $apiVersion = null,
        #[UploadedFile('avatar')] ?File $avatar = null
    ): Response {
        // Multiple parameter sources: body, headers, files
        return Response::json([
            'api_version' => $apiVersion,
            'avatar_info' => $avatar ? [
                'name' => $avatar->getClientOriginalName(),
                'size' => $avatar->getSize(),
                'mime_type' => $avatar->getClientMimeType(), // Use client mime type instead
                'error' => $avatar->getError(),
                'is_valid' => $avatar->isValid()
            ] : null,
            'all_files' => count($request->files->all()),
            'content_length' => $request->headers->get('Content-Length'),
            'request_id' => $request->headers->get('X-Request-ID'),
            'timestamp' => date('c'),
            'debug' => [
                'has_avatar' => $avatar !== null,
                'avatar_type' => $avatar ? get_class($avatar) : 'null'
            ]
        ]);
    }

    #[Post('/upload-multiple')]
    public function uploadMultiple(
        #[Body] CreateUserDTO $dto,
        #[Headers('x-api-version')] ?string $apiVersion = null,
        #[UploadedFiles] array $files = [] // array<string, UploadedFile> - PHP doesn't support generics yet
    ): Response {
        // Handle multiple file uploads
        $fileInfos = [];
        /** @var UploadedFile $file */
        foreach ($files as $key => $file) {
            if ($file && $file->isValid()) {
                $fileInfos[$key] = [
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime_type' => $file->getClientMimeType(), // Use client mime type
                    'error' => $file->getError(),
                    'is_valid' => $file->isValid()
                ];
            }
        }
        
        return Response::json([
            'user_data' => $dto->toArray(),
            'api_version' => $apiVersion,
            'uploaded_files' => $fileInfos,
            'total_files' => count($fileInfos)
        ]);
    }
}
