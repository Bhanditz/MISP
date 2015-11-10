<?php

App::uses('AppController', 'Controller');

/**
 * Tags Controller
 *
 * @property Tag $Tag
 */

class TagsController extends AppController {
	public $components = array('Security' ,'RequestHandler');

	public $paginate = array(
			'limit' => 50,
			'order' => array(
					'Tag.name' => 'asc'
			)
	);
	
	public $helpers = array('TextColour');
	
	public function beforeFilter() { // TODO REMOVE
		parent::beforeFilter();
	}
	
	public function index() {
		$this->loadModel('Event');
		$this->Event->recursive = -1;
		$this->paginate['contain'] = array('EventTag' => array('fields' => 'event_id'));
		$paginated = $this->paginate();
		foreach ($paginated as $k => &$tag) {
			$eventIDs = array();
			if (empty($tag['EventTag'])) $tag['Tag']['count'] = 0;
			else {
				foreach ($tag['EventTag'] as $eventTag) {
					$eventIDs[] = $eventTag['event_id'];
				}
				$conditions = array('Event.id' => $eventIDs);
				if (!$this->_isSiteAdmin()) $conditions = array_merge(
					$conditions,
					array('OR' => array(
						array('AND' => array(
							array('Event.distribution >' => 0),
							array('Event.published =' => 1)
						)),
						array('Event.orgc' => $this->Auth->user('org'))
					)));
				$events = $this->Event->find('all', array(
					'fields' => array('Event.id', 'Event.distribution', 'Event.orgc'),
					'conditions' => $conditions
				));
				$tag['Tag']['count'] = count($events);
			}
			unset($tag['EventTag']);
		}
		if ($this->_isRest()) {
			foreach ($paginated as &$tag) {
				$tag = $tag['Tag'];
			}
			$this->set('Tag', $paginated);
			$this->set('_serialize', array('Tag'));
		} else {
			$this->set('list', $paginated);
		}

		// send perm_tagger to view for action buttons
	}
	
	public function add() {
		if (!$this->_isSiteAdmin() && !$this->userRole['perm_tagger']) throw new NotFoundException('You don\'t have permission to do that.');
		if ($this->request->is('post')) {
			if (isset($this->request->data['Tag']['request'])) $this->request->data['Tag'] = $this->request->data['Tag']['request']; 
			if (!isset($this->request->data['Tag']['colour'])) $this->request->data['Tag']['colour'] = $this->Tag->random_color();
			if (isset($this->request->data['Tag']['id'])) unset($this->request->data['Tag']['id']);
			if ($this->Tag->save($this->request->data)) {
				if ($this->_isRest()) $this->redirect(array('action' => 'view', $this->Tag->id));
				$this->Session->setFlash('The tag has been saved.');
				$this->redirect(array('action' => 'index'));
			} else {
				if ($this->_isRest()) {
					$error_message = '';
					foreach ($this->Tag->validationErrors as $k => $v) $error_message .= '[' . $k . ']: ' . $v[0]; 
					throw new MethodNotAllowedException('Could not add the Tag. ' . $error_message);
				} else {
					$this->Session->setFlash('The tag could not be saved. Please, try again.');
				}
			}
		}
	}
	
	public function edit($id) {
		if (!$this->_isSiteAdmin() && !$this->userRole['perm_tagger']) {
			throw new NotFoundException('You don\'t have permission to do that.');
		}
		$this->Tag->id = $id;
		if (!$this->Tag->exists()) {
			throw new NotFoundException('Invalid tag');
		}
		if ($this->request->is('post') || $this->request->is('put')) {
			if (isset($this->request->data['Tag']['request'])) $this->request->data['Tag'] = $this->request->data['Tag']['request'];
			if (isset($this->request->data['Tag']['id'])) unset($this->request->data['Tag']['id']);
			if ($this->Tag->save($this->request->data)) {
				if ($this->_isRest()) $this->redirect(array('action' => 'view', $id));
				$this->Session->setFlash('The Tag has been edited');
				$this->redirect(array('action' => 'index'));
			} else {
				if ($this->_isRest()) {
					$error_message = '';
					foreach ($this->Tag->validationErrors as $k => $v) $error_message .= '[' . $k . ']: ' . $v[0];
					throw new MethodNotAllowedException('Could not add the Tag. ' . $error_message);
				}
				$this->Session->setFlash('The Tag could not be saved. Please, try again.');
			}
		}
		$this->request->data = $this->Tag->read(null, $id);
	}
	
	public function delete($id) {
		if (!$this->_isSiteAdmin() && !$this->userRole['perm_tagger']) {
			throw new NotFoundException('You don\'t have permission to do that.');
		}
		if (!$this->request->is('post')) {
			throw new MethodNotAllowedException();
		}
		$this->Tag->id = $id;
		if (!$this->Tag->exists()) {
			throw new NotFoundException('Invalid tag');
		}
		if ($this->Tag->delete()) {
			if ($this->_isRest()) {
				$this->set('name', 'Tag deleted.');
				$this->set('message', 'Tag deleted.');
				$this->set('url', '/tags/delete/' . $id);
				$this->set('_serialize', array('name', 'message', 'url'));
			}
			$this->Session->setFlash(__('Tag deleted'));
		} else {
			if ($this->_isRest()) throw new MethodNotAllowedException('Could not delete the tag, or tag doesn\'t exist.');
			$this->Session->setFlash(__('Tag was not deleted'));
		}
		if (!$this->_isRest()) $this->redirect(array('action' => 'index'));
	}
	
	public function view($id) {
		if ($this->_isRest()) {
			$tag = $this->Tag->find('first', array(
					'conditions' => array('id' => $id),
					'recursive' => -1,
					'contain' => array('EventTag' => array('fields' => 'event_id'))
			));
			if (empty($tag)) throw MethodNotAllowedException('Invalid Tag'); 
			$eventIDs = array();
			if (empty($tag['EventTag'])) $tag['Tag']['count'] = 0;
			else {
				foreach ($tag['EventTag'] as $eventTag) {
					$eventIDs[] = $eventTag['event_id'];
				}
				$conditions = array('Event.id' => $eventIDs);
				if (!$this->_isSiteAdmin()) $conditions = array_merge(
						$conditions,
						array('OR' => array(
								array('AND' => array(
										array('Event.distribution >' => 0),
										array('Event.published =' => 1)
								)),
								array('Event.orgc' => $this->Auth->user('org'))
						)));
				$events = $this->Tag->EventTag->Event->find('all', array(
						'fields' => array('Event.id', 'Event.distribution', 'Event.orgc'),
						'conditions' => $conditions
				));
				$tag['Tag']['count'] = count($events);
			}
			unset($tag['EventTag']);
			$this->set('Tag', $tag['Tag']);
			$this->set('_serialize', 'Tag');
		} else throw new MethodNotAllowedException('This action is only for REST users.');
		
	}
	
	public function showEventTag($id) {
		$this->helpers[] = 'TextColour';
		$this->loadModel('EventTag');
		$tags = $this->EventTag->find('all', array(
				'conditions' => array(
						'event_id' => $id
				),
				'contain' => 'Tag',
				'fields' => array('Tag.id', 'Tag.colour', 'Tag.name'),
		));
		$this->set('tags', $tags);
		$tags = $this->Tag->find('all', array('recursive' => -1, 'order' => array('Tag.name ASC')));
		$tagNames = array('None');
		foreach ($tags as $k => $v) {
			$tagNames[$v['Tag']['id']] = $v['Tag']['name'];
		}
		$this->set('allTags', $tagNames);
		$event['Event']['id'] = $id;
		$this->set('event', $event);
		$this->layout = 'ajax';
		$this->render('/Events/ajax/ajaxTags');
	}
	
	public function viewTag($id) {
		$tag = $this->Tag->find('first', array(
				'conditions' => array(
						'id' => $id
				),
				'recursive' => -1,
		));
		$this->layout = null;
		$this->set('tag', $tag);
		$this->set('id', $id);
		$this->render('ajax/view_tag');
	}
}
