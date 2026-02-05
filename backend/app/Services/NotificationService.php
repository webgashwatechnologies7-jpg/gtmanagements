<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;

class NotificationService
{
    /**
     * Create a notification
     */
    public static function create(array $data): Notification
    {
        return Notification::create([
            'user_id' => $data['user_id'],
            'type' => $data['type'],
            'title' => $data['title'],
            'message' => $data['message'],
            'entity_type' => $data['entity_type'] ?? null,
            'entity_id' => $data['entity_id'] ?? null,
            'data' => $data['data'] ?? null,
        ]);
    }

    /**
     * Notify user about approval request
     */
    public static function notifyApprovalRequest(User $user, $entityType, $entityId, $entityName = null): void
    {
        self::create([
            'user_id' => $user->id,
            'type' => 'approval_request',
            'title' => 'Approval Request',
            'message' => "You have a pending {$entityType} approval request" . ($entityName ? " for {$entityName}" : ''),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ]);
    }

    /**
     * Notify user about approval status
     */
    public static function notifyApprovalStatus(User $user, $entityType, $entityId, $status, $entityName = null): void
    {
        $statusText = $status === 'approved' ? 'approved' : 'rejected';
        
        self::create([
            'user_id' => $user->id,
            'type' => 'approval_status',
            'title' => ucfirst($statusText) . ' Notification',
            'message' => "Your {$entityType} has been {$statusText}" . ($entityName ? " for {$entityName}" : ''),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'data' => ['status' => $status],
        ]);
    }

    /**
     * Notify user about project assignment
     */
    public static function notifyProjectAssignment(User $user, $projectId, $projectName): void
    {
        self::create([
            'user_id' => $user->id,
            'type' => 'project_assignment',
            'title' => 'Project Assigned',
            'message' => "You have been assigned to project: {$projectName}",
            'entity_type' => 'project',
            'entity_id' => $projectId,
        ]);
    }

    /**
     * Notify user about report submission
     */
    public static function notifyReportSubmission(User $user, $reportType, $reportId, $date): void
    {
        self::create([
            'user_id' => $user->id,
            'type' => 'report_submitted',
            'title' => ucfirst($reportType) . ' Report Submitted',
            'message' => "Your {$reportType} report for {$date} has been submitted successfully",
            'entity_type' => $reportType . '_report',
            'entity_id' => $reportId,
        ]);
    }

    /**
     * Notify team lead about team member report
     */
    public static function notifyTeamLeadReport(User $teamLead, $memberName, $reportType, $reportId, $date): void
    {
        self::create([
            'user_id' => $teamLead->id,
            'type' => 'team_report_submitted',
            'title' => 'Team Member Report',
            'message' => "{$memberName} has submitted a {$reportType} report for {$date}",
            'entity_type' => $reportType . '_report',
            'entity_id' => $reportId,
        ]);
    }
}
