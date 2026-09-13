import { createMongoAbility } from '@casl/ability'

export type Actions = 'create' | 'read' | 'update' | 'delete' | 'manage' | 'validate' | 'vise' | 'act'

export type Subjects =
  | 'all'
  | 'Auth'
  | 'Parapheur'
  | 'Document'
  | 'DocumentType'
  | 'DashboardDg'
  | 'DashboardDirection'
  | 'Instruction'
  | 'Meeting'
  | 'MeetingType'
  | 'MeetingTemplate'
  | 'Appointment'
  | 'AppointmentType'
  | 'Reporting'
  | 'Structure'
  | 'User'
  | 'Role'
  | 'AuditLog'
  | 'Notification'
  | 'Delegation'
  | 'Ged'
  | 'GedAdmin'
  | 'Workspace'
  | 'WorkspaceAdmin'
  | 'Library'
  | 'Courrier'
  | 'CourrierAdmin'
  | 'DocumentTemplate'
  | 'Post'
  | 'Comment'
  | 'AclDemo'

export interface Rule {
  action: Actions
  subject: Subjects
}

export const ability = createMongoAbility<[Actions, Subjects]>()
