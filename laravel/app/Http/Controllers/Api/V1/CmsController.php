<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WebsitePage;
use App\Models\NewsPost;
use App\Models\WebsiteDownload;
use App\Models\KalpanaCircular;
use App\Models\MediaGallery;
use App\Models\MediaItem;
use App\Models\ContentApproval;
use App\Models\WebsiteSetting;
use App\Models\Event;
use App\Services\ChurchAccessService;
use App\Services\ContentApprovalService;
use App\Services\WebsitePageService;
use App\Services\NewsPostService;
use App\Services\DownloadService;
use App\Services\KalpanaCircularService;
use App\Services\MediaGalleryService;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;

class CmsController extends Controller
{
    // ==========================================
    // settings API
    // ==========================================
    public function getSettings(Request $request)
    {
        if (!$request->user()->hasPermissionTo('manage_website_settings')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $settings = WebsiteSetting::where('diocese_id', $request->user()->default_diocese_id ?? 1)->get();
        return response()->json(['success' => true, 'data' => $settings]);
    }

    public function updateSetting(Request $request)
    {
        if (!$request->user()->hasPermissionTo('manage_website_settings')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $validator = Validator::make($request->all(), [
            'key' => 'required|string',
            'value' => 'required',
            'group' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $setting = WebsiteSetting::updateOrCreate(
            [
                'diocese_id' => $request->user()->default_diocese_id ?? 1,
                'key' => $request->input('key')
            ],
            [
                'value' => $request->input('value'),
                'group' => $request->input('group'),
                'updated_by' => $request->user()->id
            ]
        );

        AuditLogService::log(
            'CMS',
            'Update Setting',
            "Updated website setting key: {$setting->key}",
            null,
            $setting->toArray(),
            $setting,
            null,
            $setting->diocese_id
        );

        return response()->json(['success' => true, 'data' => $setting]);
    }

    // ==========================================
    // Website Pages
    // ==========================================
    public function listPages(Request $request)
    {
        $query = WebsitePage::query();
        $query = ChurchAccessService::scopeQuery($request->user(), $query);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $pages = $query->orderBy('title')->get();
        return response()->json(['success' => true, 'data' => $pages]);
    }

    public function showPage(Request $request, $id)
    {
        $page = WebsitePage::findOrFail($id);
        if ($page->church_id && !ChurchAccessService::canAccessChurch($request->user(), $page->church_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        return response()->json(['success' => true, 'data' => $page]);
    }

    public function storePage(Request $request)
    {
        if (!$request->user()->hasAnyPermission(['manage_website_pages', 'submit_website_content'])) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'church_id' => 'nullable|integer',
            'page_type' => 'required|string',
            'content' => 'nullable|string',
            'excerpt' => 'nullable|string',
            'featured_image_path' => 'nullable|string',
            'meta_title' => 'nullable|string',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string',
            'visibility' => 'required|in:public,members_only,private'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $churchId = $request->input('church_id');
        if ($churchId && !ChurchAccessService::canAccessChurch($request->user(), $churchId)) {
            return response()->json(['success' => false, 'message' => 'Forbidden church scope'], 403);
        }

        $page = WebsitePageService::create($request->all(), $request->user());
        return response()->json(['success' => true, 'data' => $page], 210); // 201 Created
    }

    public function updatePage(Request $request, $id)
    {
        $page = WebsitePage::findOrFail($id);
        if ($page->church_id && !ChurchAccessService::canAccessChurch($request->user(), $page->church_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'page_type' => 'sometimes|required|string',
            'content' => 'nullable|string',
            'excerpt' => 'nullable|string',
            'visibility' => 'sometimes|required|in:public,members_only,private'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $updated = WebsitePageService::update($page, $request->all(), $request->user());
        return response()->json(['success' => true, 'data' => $updated]);
    }

    public function submitPage(Request $request, $id)
    {
        $page = WebsitePage::findOrFail($id);
        if ($page->church_id && !ChurchAccessService::canAccessChurch($request->user(), $page->church_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $remarks = $request->input('remarks');
        $approval = ContentApprovalService::submit($page, 'page_publish', $request->user(), $remarks);
        return response()->json(['success' => true, 'data' => $page, 'approval' => $approval]);
    }

    public function approvePage(Request $request, $id)
    {
        if (!$request->user()->hasPermissionTo('approve_website_content')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $page = WebsitePage::findOrFail($id);
        if ($page->church_id && !ChurchAccessService::canAccessChurch($request->user(), $page->church_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $approval = ContentApproval::where('approvable_type', WebsitePage::class)
            ->where('approvable_id', $page->id)
            ->where('status', 'pending')
            ->firstOrFail();

        ContentApprovalService::approve($approval, $request->user(), $request->input('remarks'));
        return response()->json(['success' => true, 'data' => $page]);
    }

    public function rejectPage(Request $request, $id)
    {
        if (!$request->user()->hasPermissionTo('approve_website_content')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $page = WebsitePage::findOrFail($id);
        if ($page->church_id && !ChurchAccessService::canAccessChurch($request->user(), $page->church_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $reason = $request->input('rejection_reason');
        if (empty($reason)) {
            return response()->json(['success' => false, 'message' => 'Rejection reason is required'], 422);
        }

        $approval = ContentApproval::where('approvable_type', WebsitePage::class)
            ->where('approvable_id', $page->id)
            ->where('status', 'pending')
            ->firstOrFail();

        ContentApprovalService::reject($approval, $request->user(), $reason);
        return response()->json(['success' => true, 'data' => $page]);
    }

    public function publishPage(Request $request, $id)
    {
        if (!$request->user()->hasPermissionTo('publish_website_content')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $page = WebsitePage::findOrFail($id);
        if ($page->church_id && !ChurchAccessService::canAccessChurch($request->user(), $page->church_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        ContentApprovalService::publish($page, $request->user());
        return response()->json(['success' => true, 'data' => $page]);
    }

    public function archivePage(Request $request, $id)
    {
        if (!$request->user()->hasPermissionTo('publish_website_content')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $page = WebsitePage::findOrFail($id);
        if ($page->church_id && !ChurchAccessService::canAccessChurch($request->user(), $page->church_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        ContentApprovalService::archive($page, $request->user());
        return response()->json(['success' => true, 'data' => $page]);
    }

    // ==========================================
    // News Posts
    // ==========================================
    public function listNews(Request $request)
    {
        $query = NewsPost::query();
        $query = ChurchAccessService::scopeQuery($request->user(), $query);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $news = $query->orderBy('created_at', 'desc')->get();
        return response()->json(['success' => true, 'data' => $news]);
    }

    public function showNews(Request $request, $id)
    {
        $post = NewsPost::findOrFail($id);
        if ($post->church_id && !ChurchAccessService::canAccessChurch($request->user(), $post->church_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        return response()->json(['success' => true, 'data' => $post]);
    }

    public function storeNews(Request $request)
    {
        if (!$request->user()->hasAnyPermission(['manage_news', 'submit_website_content'])) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'church_id' => 'nullable|integer',
            'content' => 'required|string',
            'excerpt' => 'nullable|string',
            'category' => 'required|string',
            'language' => 'required|in:en,ml,de',
            'visibility' => 'required|in:public,members_only,private'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $churchId = $request->input('church_id');
        if ($churchId && !ChurchAccessService::canAccessChurch($request->user(), $churchId)) {
            return response()->json(['success' => false, 'message' => 'Forbidden church scope'], 403);
        }

        $post = NewsPostService::create($request->all(), $request->user());
        return response()->json(['success' => true, 'data' => $post], 210);
    }

    public function updateNews(Request $request, $id)
    {
        $post = NewsPost::findOrFail($id);
        if ($post->church_id && !ChurchAccessService::canAccessChurch($request->user(), $post->church_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|string',
            'category' => 'sometimes|required|string',
            'language' => 'sometimes|required|in:en,ml,de',
            'visibility' => 'sometimes|required|in:public,members_only,private'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $updated = NewsPostService::update($post, $request->all(), $request->user());
        return response()->json(['success' => true, 'data' => $updated]);
    }

    public function submitNews(Request $request, $id)
    {
        $post = NewsPost::findOrFail($id);
        if ($post->church_id && !ChurchAccessService::canAccessChurch($request->user(), $post->church_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $approval = ContentApprovalService::submit($post, 'news_publish', $request->user(), $request->input('remarks'));
        return response()->json(['success' => true, 'data' => $post, 'approval' => $approval]);
    }

    public function approveNews(Request $request, $id)
    {
        if (!$request->user()->hasPermissionTo('approve_website_content')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $post = NewsPost::findOrFail($id);
        if ($post->church_id && !ChurchAccessService::canAccessChurch($request->user(), $post->church_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $approval = ContentApproval::where('approvable_type', NewsPost::class)
            ->where('approvable_id', $post->id)
            ->where('status', 'pending')
            ->firstOrFail();

        ContentApprovalService::approve($approval, $request->user(), $request->input('remarks'));
        return response()->json(['success' => true, 'data' => $post]);
    }

    public function rejectNews(Request $request, $id)
    {
        if (!$request->user()->hasPermissionTo('approve_website_content')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $post = NewsPost::findOrFail($id);
        if ($post->church_id && !ChurchAccessService::canAccessChurch($request->user(), $post->church_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $reason = $request->input('rejection_reason');
        if (empty($reason)) {
            return response()->json(['success' => false, 'message' => 'Rejection reason is required'], 422);
        }

        $approval = ContentApproval::where('approvable_type', NewsPost::class)
            ->where('approvable_id', $post->id)
            ->where('status', 'pending')
            ->firstOrFail();

        ContentApprovalService::reject($approval, $request->user(), $reason);
        return response()->json(['success' => true, 'data' => $post]);
    }

    public function publishNews(Request $request, $id)
    {
        if (!$request->user()->hasPermissionTo('publish_news')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $post = NewsPost::findOrFail($id);
        if ($post->church_id && !ChurchAccessService::canAccessChurch($request->user(), $post->church_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        ContentApprovalService::publish($post, $request->user());
        return response()->json(['success' => true, 'data' => $post]);
    }

    public function archiveNews(Request $request, $id)
    {
        if (!$request->user()->hasPermissionTo('publish_news')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $post = NewsPost::findOrFail($id);
        if ($post->church_id && !ChurchAccessService::canAccessChurch($request->user(), $post->church_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        ContentApprovalService::archive($post, $request->user());
        return response()->json(['success' => true, 'data' => $post]);
    }

    // ==========================================
    // Downloads
    // ==========================================
    public function listDownloads(Request $request)
    {
        $query = WebsiteDownload::query();
        $query = ChurchAccessService::scopeQuery($request->user(), $query);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $downloads = $query->orderBy('created_at', 'desc')->get();
        return response()->json(['success' => true, 'data' => $downloads]);
    }

    public function showDownload(Request $request, $id)
    {
        $download = WebsiteDownload::findOrFail($id);
        if ($download->church_id && !ChurchAccessService::canAccessChurch($request->user(), $download->church_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        return response()->json(['success' => true, 'data' => $download]);
    }

    public function storeDownload(Request $request)
    {
        if (!$request->user()->hasAnyPermission(['manage_downloads', 'submit_website_content'])) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'church_id' => 'nullable|integer',
            'description' => 'nullable|string',
            'download_type' => 'required|string',
            'file' => 'required|file|max:20480', // 20MB limit
            'visibility' => 'required|in:public,members_only,admins_only'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $churchId = $request->input('church_id');
        if ($churchId && !ChurchAccessService::canAccessChurch($request->user(), $churchId)) {
            return response()->json(['success' => false, 'message' => 'Forbidden church scope'], 403);
        }

        $file = $request->file('file');
        $download = DownloadService::create($request->all(), $file, $request->user());
        return response()->json(['success' => true, 'data' => $download], 210);
    }

    public function updateDownload(Request $request, $id)
    {
        $download = WebsiteDownload::findOrFail($id);
        if ($download->church_id && !ChurchAccessService::canAccessChurch($request->user(), $download->church_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'download_type' => 'sometimes|required|string',
            'file' => 'nullable|file|max:20480',
            'visibility' => 'sometimes|required|in:public,members_only,admins_only'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $file = $request->file('file');
        $updated = DownloadService::update($download, $request->all(), $file, $request->user());
        return response()->json(['success' => true, 'data' => $updated]);
    }

    public function submitDownload(Request $request, $id)
    {
        $download = WebsiteDownload::findOrFail($id);
        if ($download->church_id && !ChurchAccessService::canAccessChurch($request->user(), $download->church_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $approval = ContentApprovalService::submit($download, 'download_publish', $request->user(), $request->input('remarks'));
        return response()->json(['success' => true, 'data' => $download, 'approval' => $approval]);
    }

    public function approveDownload(Request $request, $id)
    {
        if (!$request->user()->hasPermissionTo('approve_website_content')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $download = WebsiteDownload::findOrFail($id);
        if ($download->church_id && !ChurchAccessService::canAccessChurch($request->user(), $download->church_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $approval = ContentApproval::where('approvable_type', WebsiteDownload::class)
            ->where('approvable_id', $download->id)
            ->where('status', 'pending')
            ->firstOrFail();

        ContentApprovalService::approve($approval, $request->user(), $request->input('remarks'));
        return response()->json(['success' => true, 'data' => $download]);
    }

    public function rejectDownload(Request $request, $id)
    {
        if (!$request->user()->hasPermissionTo('approve_website_content')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $download = WebsiteDownload::findOrFail($id);
        if ($download->church_id && !ChurchAccessService::canAccessChurch($request->user(), $download->church_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $reason = $request->input('rejection_reason');
        if (empty($reason)) {
            return response()->json(['success' => false, 'message' => 'Rejection reason is required'], 422);
        }

        $approval = ContentApproval::where('approvable_type', WebsiteDownload::class)
            ->where('approvable_id', $download->id)
            ->where('status', 'pending')
            ->firstOrFail();

        ContentApprovalService::reject($approval, $request->user(), $reason);
        return response()->json(['success' => true, 'data' => $download]);
    }

    public function publishDownload(Request $request, $id)
    {
        if (!$request->user()->hasPermissionTo('publish_downloads')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $download = WebsiteDownload::findOrFail($id);
        if ($download->church_id && !ChurchAccessService::canAccessChurch($request->user(), $download->church_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        ContentApprovalService::publish($download, $request->user());
        return response()->json(['success' => true, 'data' => $download]);
    }

    public function archiveDownload(Request $request, $id)
    {
        if (!$request->user()->hasPermissionTo('publish_downloads')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $download = WebsiteDownload::findOrFail($id);
        if ($download->church_id && !ChurchAccessService::canAccessChurch($request->user(), $download->church_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        ContentApprovalService::archive($download, $request->user());
        return response()->json(['success' => true, 'data' => $download]);
    }

    public function userDownloadFile(Request $request, $id)
    {
        $download = WebsiteDownload::findOrFail($id);
        
        // Scope boundary check
        if ($download->church_id && !ChurchAccessService::canAccessChurch($request->user(), $download->church_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        // Validate visibility rules
        if ($download->visibility === 'admins_only' && !$request->user()->hasRole(['Super Admin', 'Diocese Admin', 'Diocese PRO'])) {
            return response()->json(['success' => false, 'message' => 'Access denied'], 403);
        }

        if (!Storage::exists($download->file_path)) {
            return response()->json(['success' => false, 'message' => 'File not found'], 404);
        }

        DownloadService::incrementCount($download);
        return Storage::download($download->file_path, $download->file_name);
    }

    // ==========================================
    // Kalpana / Circulars
    // ==========================================
    public function listKalpanas(Request $request)
    {
        $query = KalpanaCircular::query();
        $query = ChurchAccessService::scopeQuery($request->user(), $query);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $circulars = $query->orderBy('circular_date', 'desc')->get();
        return response()->json(['success' => true, 'data' => $circulars]);
    }

    public function showKalpana(Request $request, $id)
    {
        $circular = KalpanaCircular::findOrFail($id);
        if ($circular->church_id && !ChurchAccessService::canAccessChurch($request->user(), $circular->church_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        return response()->json(['success' => true, 'data' => $circular]);
    }

    public function storeKalpana(Request $request)
    {
        if (!$request->user()->hasAnyPermission(['manage_kalpana_circulars', 'submit_website_content'])) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'church_id' => 'nullable|integer',
            'circular_type' => 'required|string',
            'circular_date' => 'required|date',
            'reference_number' => 'nullable|string',
            'content' => 'nullable|string',
            'file' => 'nullable|file|max:10240',
            'visibility' => 'required|in:public,members_only,clergy_only,admins_only'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $churchId = $request->input('church_id');
        if ($churchId && !ChurchAccessService::canAccessChurch($request->user(), $churchId)) {
            return response()->json(['success' => false, 'message' => 'Forbidden church scope'], 403);
        }

        $file = $request->file('file');
        $circular = KalpanaCircularService::create($request->all(), $file, $request->user());
        return response()->json(['success' => true, 'data' => $circular], 210);
    }

    public function updateKalpana(Request $request, $id)
    {
        $circular = KalpanaCircular::findOrFail($id);
        if ($circular->church_id && !ChurchAccessService::canAccessChurch($request->user(), $circular->church_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'circular_date' => 'sometimes|required|date',
            'file' => 'nullable|file|max:10240',
            'visibility' => 'sometimes|required|in:public,members_only,clergy_only,admins_only'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $file = $request->file('file');
        $updated = KalpanaCircularService::update($circular, $request->all(), $file, $request->user());
        return response()->json(['success' => true, 'data' => $updated]);
    }

    public function submitKalpana(Request $request, $id)
    {
        $circular = KalpanaCircular::findOrFail($id);
        if ($circular->church_id && !ChurchAccessService::canAccessChurch($request->user(), $circular->church_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $approval = ContentApprovalService::submit($circular, 'circular_publish', $request->user(), $request->input('remarks'));
        return response()->json(['success' => true, 'data' => $circular, 'approval' => $approval]);
    }

    public function approveKalpana(Request $request, $id)
    {
        if (!$request->user()->hasPermissionTo('approve_website_content')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $circular = KalpanaCircular::findOrFail($id);
        if ($circular->church_id && !ChurchAccessService::canAccessChurch($request->user(), $circular->church_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $approval = ContentApproval::where('approvable_type', KalpanaCircular::class)
            ->where('approvable_id', $circular->id)
            ->where('status', 'pending')
            ->firstOrFail();

        ContentApprovalService::approve($approval, $request->user(), $request->input('remarks'));
        return response()->json(['success' => true, 'data' => $circular]);
    }

    public function rejectKalpana(Request $request, $id)
    {
        if (!$request->user()->hasPermissionTo('approve_website_content')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $circular = KalpanaCircular::findOrFail($id);
        if ($circular->church_id && !ChurchAccessService::canAccessChurch($request->user(), $circular->church_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $reason = $request->input('rejection_reason');
        if (empty($reason)) {
            return response()->json(['success' => false, 'message' => 'Rejection reason is required'], 422);
        }

        $approval = ContentApproval::where('approvable_type', KalpanaCircular::class)
            ->where('approvable_id', $circular->id)
            ->where('status', 'pending')
            ->firstOrFail();

        ContentApprovalService::reject($approval, $request->user(), $reason);
        return response()->json(['success' => true, 'data' => $circular]);
    }

    public function publishKalpana(Request $request, $id)
    {
        if (!$request->user()->hasPermissionTo('publish_kalpana_circulars')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $circular = KalpanaCircular::findOrFail($id);
        if ($circular->church_id && !ChurchAccessService::canAccessChurch($request->user(), $circular->church_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        ContentApprovalService::publish($circular, $request->user());
        return response()->json(['success' => true, 'data' => $circular]);
    }

    public function archiveKalpana(Request $request, $id)
    {
        if (!$request->user()->hasPermissionTo('publish_kalpana_circulars')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $circular = KalpanaCircular::findOrFail($id);
        if ($circular->church_id && !ChurchAccessService::canAccessChurch($request->user(), $circular->church_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        ContentApprovalService::archive($circular, $request->user());
        return response()->json(['success' => true, 'data' => $circular]);
    }

    // ==========================================
    // Photo/Video Galleries & Items
    // ==========================================
    public function listGalleries(Request $request)
    {
        $query = MediaGallery::query();
        $query = ChurchAccessService::scopeQuery($request->user(), $query);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $galleries = $query->orderBy('created_at', 'desc')->get();
        return response()->json(['success' => true, 'data' => $galleries]);
    }

    public function showGallery(Request $request, $id)
    {
        $gallery = MediaGallery::with('items')->findOrFail($id);
        if ($gallery->church_id && !ChurchAccessService::canAccessChurch($request->user(), $gallery->church_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        return response()->json(['success' => true, 'data' => $gallery]);
    }

    public function storeGallery(Request $request)
    {
        if (!$request->user()->hasAnyPermission(['manage_galleries', 'submit_website_content'])) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'church_id' => 'nullable|integer',
            'event_id' => 'nullable|integer',
            'ministry_unit_id' => 'nullable|integer',
            'description' => 'nullable|string',
            'gallery_type' => 'required|in:photo,video,mixed',
            'visibility' => 'required|in:public,members_only,private'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $churchId = $request->input('church_id');
        if ($churchId && !ChurchAccessService::canAccessChurch($request->user(), $churchId)) {
            return response()->json(['success' => false, 'message' => 'Forbidden church scope'], 403);
        }

        $gallery = MediaGalleryService::create($request->all(), $request->user());
        return response()->json(['success' => true, 'data' => $gallery], 210);
    }

    public function updateGallery(Request $request, $id)
    {
        $gallery = MediaGallery::findOrFail($id);
        if ($gallery->church_id && !ChurchAccessService::canAccessChurch($request->user(), $gallery->church_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'gallery_type' => 'sometimes|required|in:photo,video,mixed',
            'visibility' => 'sometimes|required|in:public,members_only,private'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $updated = MediaGalleryService::update($gallery, $request->all(), $request->user());
        return response()->json(['success' => true, 'data' => $updated]);
    }

    public function submitGallery(Request $request, $id)
    {
        $gallery = MediaGallery::findOrFail($id);
        if ($gallery->church_id && !ChurchAccessService::canAccessChurch($request->user(), $gallery->church_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $approval = ContentApprovalService::submit($gallery, 'gallery_publish', $request->user(), $request->input('remarks'));
        return response()->json(['success' => true, 'data' => $gallery, 'approval' => $approval]);
    }

    public function approveGallery(Request $request, $id)
    {
        if (!$request->user()->hasPermissionTo('approve_website_content')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $gallery = MediaGallery::findOrFail($id);
        if ($gallery->church_id && !ChurchAccessService::canAccessChurch($request->user(), $gallery->church_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $approval = ContentApproval::where('approvable_type', MediaGallery::class)
            ->where('approvable_id', $gallery->id)
            ->where('status', 'pending')
            ->firstOrFail();

        ContentApprovalService::approve($approval, $request->user(), $request->input('remarks'));
        // Move images to public storage on approval
        MediaGalleryService::publishGalleryAssets($gallery);
        
        return response()->json(['success' => true, 'data' => $gallery]);
    }

    public function rejectGallery(Request $request, $id)
    {
        if (!$request->user()->hasPermissionTo('approve_website_content')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $gallery = MediaGallery::findOrFail($id);
        if ($gallery->church_id && !ChurchAccessService::canAccessChurch($request->user(), $gallery->church_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $reason = $request->input('rejection_reason');
        if (empty($reason)) {
            return response()->json(['success' => false, 'message' => 'Rejection reason is required'], 422);
        }

        $approval = ContentApproval::where('approvable_type', MediaGallery::class)
            ->where('approvable_id', $gallery->id)
            ->where('status', 'pending')
            ->firstOrFail();

        ContentApprovalService::reject($approval, $request->user(), $reason);
        return response()->json(['success' => true, 'data' => $gallery]);
    }

    public function publishGallery(Request $request, $id)
    {
        if (!$request->user()->hasPermissionTo('publish_galleries')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $gallery = MediaGallery::findOrFail($id);
        if ($gallery->church_id && !ChurchAccessService::canAccessChurch($request->user(), $gallery->church_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        ContentApprovalService::publish($gallery, $request->user());
        MediaGalleryService::publishGalleryAssets($gallery);
        
        return response()->json(['success' => true, 'data' => $gallery]);
    }

    public function archiveGallery(Request $request, $id)
    {
        if (!$request->user()->hasPermissionTo('publish_galleries')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $gallery = MediaGallery::findOrFail($id);
        if ($gallery->church_id && !ChurchAccessService::canAccessChurch($request->user(), $gallery->church_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        ContentApprovalService::archive($gallery, $request->user());
        return response()->json(['success' => true, 'data' => $gallery]);
    }

    public function addGalleryItem(Request $request, $id)
    {
        $gallery = MediaGallery::findOrFail($id);
        if ($gallery->church_id && !ChurchAccessService::canAccessChurch($request->user(), $gallery->church_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $validator = Validator::make($request->all(), [
            'media_type' => 'required|in:image,video',
            'file' => 'required_if:media_type,image|file|image|max:10240',
            'external_video_url' => 'required_if:media_type,video|url',
            'title' => 'nullable|string',
            'caption' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'tagged_member_ids' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        if ($request->input('media_type') === 'image') {
            $file = $request->file('file');
            $item = MediaGalleryService::addImageItem($gallery, $file, $request->all(), $request->user());
        } else {
            $item = MediaGalleryService::addVideoItem($gallery, $request->input('external_video_url'), $request->all(), $request->user());
            if (!$item) {
                return response()->json(['success' => false, 'message' => 'Invalid external video provider URL.'], 422);
            }
        }

        return response()->json(['success' => true, 'data' => $item]);
    }

    public function updateGalleryItem(Request $request, $itemId)
    {
        $item = MediaItem::findOrFail($itemId);
        $gallery = $item->gallery;
        if ($gallery->church_id && !ChurchAccessService::canAccessChurch($request->user(), $gallery->church_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string',
            'caption' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'tagged_member_ids' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $item->update($request->only(['title', 'caption', 'sort_order']));
        if ($request->has('tagged_member_ids')) {
            $item->taggedMembers()->sync($request->input('tagged_member_ids'));
        }

        return response()->json(['success' => true, 'data' => $item]);
    }

    public function archiveGalleryItem(Request $request, $itemId)
    {
        $item = MediaItem::findOrFail($itemId);
        $gallery = $item->gallery;
        if ($gallery->church_id && !ChurchAccessService::canAccessChurch($request->user(), $gallery->church_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $item->update(['status' => 'archived']);
        return response()->json(['success' => true, 'data' => $item]);
    }

    // ==========================================
    // Approvals queue
    // ==========================================
    public function listApprovals(Request $request)
    {
        if (!$request->user()->hasPermissionTo('approve_website_content')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $query = ContentApproval::with(['requester', 'diocese', 'church']);
        $query = ChurchAccessService::scopeQuery($request->user(), $query);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        } else {
            $query->where('status', 'pending');
        }

        $approvals = $query->orderBy('created_at', 'desc')->get();
        return response()->json(['success' => true, 'data' => $approvals]);
    }

    public function processApprovalDecision(Request $request, $id)
    {
        if (!$request->user()->hasPermissionTo('approve_website_content')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $approval = ContentApproval::findOrFail($id);
        if ($approval->church_id && !ChurchAccessService::canAccessChurch($request->user(), $approval->church_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:approve,reject',
            'remarks' => 'nullable|string',
            'rejection_reason' => 'required_if:action,reject|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        if ($request->input('action') === 'approve') {
            ContentApprovalService::approve($approval, $request->user(), $request->input('remarks'));
            
            // If it is media gallery, move files to public
            if ($approval->approvable_type === MediaGallery::class) {
                MediaGalleryService::publishGalleryAssets($approval->approvable);
            }
        } else {
            ContentApprovalService::reject($approval, $request->user(), $request->input('rejection_reason'));
        }

        return response()->json(['success' => true, 'message' => 'Approval processed successfully.']);
    }

    // ==========================================
    // CMS reports
    // ==========================================
    public function cmsReports(Request $request)
    {
        if (!$request->user()->hasPermissionTo('view_cms_reports')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $accessibleChurchIds = ChurchAccessService::getAccessibleChurchIds($request->user());
        
        $pagesQuery = WebsitePage::query();
        $newsQuery = NewsPost::query();
        $downloadsQuery = WebsiteDownload::query();
        $galleriesQuery = MediaGallery::query();

        if ($accessibleChurchIds !== null) {
            $pagesQuery->whereIn('church_id', $accessibleChurchIds);
            $newsQuery->whereIn('church_id', $accessibleChurchIds);
            $downloadsQuery->whereIn('church_id', $accessibleChurchIds);
            $galleriesQuery->whereIn('church_id', $accessibleChurchIds);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total_pages' => $pagesQuery->count(),
                'published_pages' => (clone $pagesQuery)->where('status', 'published')->count(),
                'total_news' => $newsQuery->count(),
                'published_news' => (clone $newsQuery)->where('status', 'published')->count(),
                'total_downloads' => $downloadsQuery->count(),
                'published_downloads' => (clone $downloadsQuery)->where('status', 'published')->count(),
                'total_galleries' => $galleriesQuery->count(),
                'published_galleries' => (clone $galleriesQuery)->where('status', 'published')->count(),
                'total_downloads_clicks' => $downloadsQuery->sum('download_count')
            ]
        ]);
    }
}
