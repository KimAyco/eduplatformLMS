<?php

/**
 * @return array{role: string, list_url: string, dashboard_url: string}
 */
function resourcesPageContext(): array
{
    $user = currentUser();
    $role = $user['role'] ?? '';
    if ($role === 'school_admin') {
        return [
            'role' => 'school_admin',
            'list_url' => 'school/resources.php',
            'dashboard_url' => 'school/dashboard.php',
        ];
    }
    return [
        'role' => 'teacher',
        'list_url' => 'teacher/resources.php',
        'dashboard_url' => 'teacher/dashboard.php',
    ];
}

function handleResourcesPost(string $listUrl): void
{
    verifyCsrf();
    $user = currentUser();
    $sid = schoolId();
    $userId = (int) $user['id'];
    $action = $_POST['form_action'] ?? '';

    try {
        if ($action === 'create_deck') {
            $id = ContentResourceRepository::create($sid, $userId, [
                'title' => 'Untitled presentation',
                'resource_type' => 'deck',
                'content' => defaultDeckContent(),
            ]);
            redirect('resource-deck-editor.php?id=' . $id);
        } elseif ($action === 'create_doc') {
            $id = ContentResourceRepository::create($sid, $userId, [
                'title' => 'Untitled document',
                'resource_type' => 'doc',
                'content' => '<p></p>',
            ]);
            redirect('resource-doc-editor.php?id=' . $id);
        } elseif ($action === 'duplicate') {
            $resourceId = (int) ($_POST['resource_id'] ?? 0);
            $resource = ContentResourceRepository::get($resourceId, $sid);
            if (!$resource || !canAccessContentResource($resource)) {
                throw new InvalidArgumentException('Resource not found.');
            }
            $newId = ContentResourceRepository::duplicate($resourceId, $sid, $userId);
            flash('success', 'Resource duplicated.');
            redirect(contentResourceEditorUrl($newId, $resource['resource_type']));
        } elseif ($action === 'archive') {
            $resourceId = (int) ($_POST['resource_id'] ?? 0);
            $resource = ContentResourceRepository::get($resourceId, $sid);
            if (!$resource || !canAccessContentResource($resource)) {
                throw new InvalidArgumentException('Resource not found.');
            }
            ContentResourceRepository::archive($resourceId, $sid);
            flash('success', 'Resource archived.');
        } elseif ($action === 'bulk_archive') {
            $ids = $_POST['resource_ids'] ?? [];
            if (!is_array($ids)) {
                $ids = [];
            }
            $count = 0;
            foreach ($ids as $rid) {
                $resourceId = (int) $rid;
                $resource = ContentResourceRepository::get($resourceId, $sid);
                if ($resource && canAccessContentResource($resource) && $resource['status'] !== 'archived') {
                    ContentResourceRepository::archive($resourceId, $sid);
                    $count++;
                }
            }
            flash('success', $count . ' resource(s) archived.');
        } elseif ($action === 'restore') {
            $resourceId = (int) ($_POST['resource_id'] ?? 0);
            $resource = ContentResourceRepository::get($resourceId, $sid);
            if (!$resource || !canAccessContentResource($resource)) {
                throw new InvalidArgumentException('Resource not found.');
            }
            ContentResourceRepository::restore($resourceId, $sid);
            flash('success', 'Resource restored.');
        } elseif ($action === 'attach_to_class') {
            if (($user['role'] ?? '') !== 'teacher') {
                throw new InvalidArgumentException('Only teachers can attach resources to classes.');
            }
            $resourceId = (int) ($_POST['resource_id'] ?? 0);
            $classId = (int) ($_POST['class_id'] ?? 0);
            $sectionId = (int) ($_POST['section_id'] ?? 0) ?: null;
            requireClassAccess($classId, 'teacher');
            ContentResourceRepository::attachToClass($resourceId, $classId, $sectionId, $userId, $sid);
            flash('success', 'Resource added to your class.');
            redirect('teacher/course.php?id=' . $classId);
        } elseif ($action === 'share_to_library') {
            $resourceId = (int) ($_POST['resource_id'] ?? 0);
            $resource = ContentResourceRepository::get($resourceId, $sid);
            if (!$resource || !canAccessContentResource($resource)) {
                throw new InvalidArgumentException('Resource not found.');
            }
            ContentResourceRepository::shareToLibrary($resourceId, $sid, $userId, [
                'description' => trim($_POST['description'] ?? '') ?: null,
                'resource_kind' => $_POST['resource_kind'] ?? 'lesson',
                'subject_id' => (int) ($_POST['subject_id'] ?? 0) ?: null,
                'audience' => $_POST['audience'] ?? 'all',
            ]);
            flash('success', 'Resource submitted to the Virtual Library for approval.');
        }
    } catch (InvalidArgumentException $e) {
        flash('error', $e->getMessage());
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    }

    redirect($listUrl);
}
