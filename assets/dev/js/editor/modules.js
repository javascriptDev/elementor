import EditorModule from './utils/module';
import Introduction from './utils/introduction';
import ControlsStack from './views/controls-stack';
import BaseSettings from './elements/models/base-settings';
import Container from './container/container';
import DocumentCommandsBase from './document/commands/base';

elementorModules.editor = {
	elements: {
		models: {
			BaseSettings: BaseSettings,
		},
	},
	utils: {
		Module: EditorModule,
		Introduction: Introduction,
	},
	views: {
		ControlsStack: ControlsStack,
	},

	document: {
		CommandBase: DocumentCommandsBase,
	},

	Container: Container,
};
