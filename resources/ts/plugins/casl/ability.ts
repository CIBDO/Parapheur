import { createMongoAbility } from '@casl/ability';

export type Actions = 'create' | 'read' | 'update' | 'delete' | 'manage';

export type Subjects =
  | 'all'
  | 'Auth'
  | 'Parapheur'
  | 'Document'
  | 'DocumentType'
  | 'DashboardDg'
  | 'Instruction'
  | 'Meeting'
  | 'MeetingType'
  | 'MeetingTemplate'
  | 'Reporting'
  | 'Post'
  | 'Comment'
  | 'AclDemo';

export interface Rule {
  action: Actions;
  subject: Subjects;
}

export const ability = createMongoAbility<[Actions, Subjects]>();
