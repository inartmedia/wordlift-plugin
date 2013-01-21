angular.element(document).ready ->
	angular
		.module( "wordliftApp", [] )
		.constant( "BASE_URL", "../wp-content/plugins/wordlift/html/" )
		.constant( "ENTITIES_DEFAULT_LIMIT", 10)
		.constant( "ENTITY_DEFAULT_LIMIT", 100)
		.constant( "DEFAULT_LIMIT", 10)
		.config( [ "BASE_URL", "$httpProvider", "$locationProvider", "$routeProvider", ( BASE_URL, $httpProvider, $locationProvider, $routeProvider ) ->

			# $locationProvider.html5Mode false

			$routeProvider
				.when "/list"
					templateUrl: BASE_URL + "/entities-list.html"
					controller: "EntitiesCtrl"
				.when "/edit/:subject"				
					templateUrl: BASE_URL + "/entities-edit.html"
					controller: "EntityCtrl"
				.otherwise
					redirectTo: "/list"

		])
		.controller( "TasksCtrl", [ "ApiService", "$location", "$routeParams", "$scope", "$log", ( ApiService, $location, $routeParams, $scope, $log ) ->

			event = "tasks"
			$scope.data = []
			$scope.state = "running"

			$scope.$on event, (event, data) ->
				$scope.data = data

			$scope.goToPage = (page) ->
				ApiService.list "wordlift.tasks", event, { "state": $scope.state }, page * 10

			$scope.select = (state) ->
				$scope.state = state
				$scope.goToPage 0

			$scope.goToPage 0

		])
		.controller( "EntityCtrl", [ "ENTITY_DEFAULT_LIMIT", "EntitiesService", "$location", "$routeParams", "$scope", "$log", ( ENTITY_DEFAULT_LIMIT, EntitiesService, $location, $routeParams, $scope, $log ) ->

			event = "entity"
			subject = $routeParams[ "subject" ]
			$scope.languages = []
			$scope.currentLanguage = "";
			$scope.isNewPropertyVisible = false

			$scope.$on event, (event, data) ->
				$scope.data = data
				$scope.languages = [ "(none)" ]
				( $scope.languages.push entity.lang if entity.lang and -1 is $scope.languages.indexOf entity.lang ) for entity in data.content
				$scope.languages = $scope.languages.sort()

			$scope.showNewProperty = ->
				$scope.isNewPropertyVisible = not $scope.isNewPropertyVisible

			$scope.setLanguage = (language) ->
				$scope.currentLanguage = if language isnt "(none)" then language else ""

			$scope.isCurrentLanguage = (lang) ->
				return true if $scope.currentLanguage is "" and ( not lang? or lang is "(none)" )
				$scope.currentLanguage is lang

			$scope.$on "property.update", (event, data) ->
				$scope.goToPage 0

			$scope.goToPreviousPage = ->
				$scope.goToPage ( $scope.data.page - 1 )

			$scope.goToNextPage = ->
				$scope.goToPage ( $scope.data.page + 1 )

			$scope.getPages = ->
				[1 .. $scope.data.pages]

			$scope.goToPage = (page) ->
				EntitiesService.list "wordlift.entity", event, { "subject": subject }, page * ENTITY_DEFAULT_LIMIT, ENTITY_DEFAULT_LIMIT

			$scope.isImage = (url) ->
				return if not url?
				matches = url.match /\.(jpg|jpeg|svg|gif|png)$/i
				matches?

			$scope.setEditMode = ( property, editMode ) ->
				$scope.tmp = { property: angular.copy property } if editMode
				property.editMode = editMode

			$scope.saveProperty = ( property ) ->
				EntitiesService.list "wordlift.property", "property.update", { "subject": subject }, null, null, "DELETE", $scope.tmp.property
				$scope.saveNewProperty property

			$scope.saveNewProperty = ( property, callback ) ->
				EntitiesService.list "wordlift.property", "property.update", { "subject": subject }, null, null, "POST", property
				callback() if callback?

			$scope.clearNewProperty = ->
				$scope.newProperty =
					key: ""
					lang: ""
					type: ""
					value: ""


			$scope.deleteProperty = ( property ) ->
				$scope.tmp = { property: property }
				Avgrund.show "#delete-confirmation-dialog"

			$scope.cancelDeleteProperty = ->
				delete $scope.tmp.property
				Avgrund.hide "#delete-confirmation-dialog"

			$scope.confirmDeleteProperty = ->
				Avgrund.hide "#delete-confirmation-dialog"
				property = $scope.tmp.property
				EntitiesService.list "wordlift.property", "property.update", { "subject": subject }, null, null, "DELETE", property

			$scope.goToPage 0

		])
		.controller( "EntitiesCtrl", [ "ENTITIES_DEFAULT_LIMIT", "EntitiesService", "$location", "$scope", "$log", ( ENTITIES_DEFAULT_LIMIT, EntitiesService, $location, $scope, $log ) ->

			$scope.filters = 
				nameFilter : ""
				languagesFilter: ""

			$scope.data =
				page: 0
				pages: 1
				content: []

			$scope.$on "entities", (event, data) ->
				$scope.data = data

			$scope.getTypeName = (type) ->
				matches = type.match /([^\/]*)$/i
				matches[0] if matches?

			$scope.getEntityName = (entity) ->
				matches = entity.subject.match /([^\/]*)$/i
				matches[0] if matches?					

			$scope.getPages = ->
				[1 .. $scope.data.pages]

			$scope.goToPage = (page) ->
				EntitiesService.list "wordlift.entities", "entities", { name: $scope.filters.nameFilter, languages: $scope.filters.languagesFilter }, page * ENTITIES_DEFAULT_LIMIT, ENTITIES_DEFAULT_LIMIT

			$scope.goToPreviousPage = ->
				$scope.goToPage ( $scope.data.page - 1 )

			$scope.goToNextPage = ->
				$scope.goToPage ( $scope.data.page + 1 )

			$scope.goToEntity = (entity) ->
				$location.path "/edit/" + $scope.getEntityName( entity )

			$scope.filter = ->
				$scope.goToPage 0				

			$scope.goToPage 0

		])
		.service( "ApiService", [ "DEFAULT_LIMIT", "$http", "$rootScope", "$log", ( DEFAULT_LIMIT, $http, $rootScope, $log ) ->

			list: ( action, event, params = [], offset = 0, limit = DEFAULT_LIMIT, method = "GET", data = null ) ->

				params[ "action" ] = action
				params[ "limit" ] = limit
				params[ "offset" ] = offset

				$http
					method: method
					url: "admin-ajax.php"
					params: params
					data: data
				.success ( data, status, headers, config ) ->
					$rootScope.$broadcast event, data
				.error ( data, status, headers, config ) ->
					$rootScope.$broadcast "error", data

		])
		.service( "EntitiesService", [ "DEFAULT_LIMIT", "$http", "$rootScope", "$log", ( DEFAULT_LIMIT, $http, $rootScope, $log ) ->

			list: ( action, event, params = [], offset = 0, limit = DEFAULT_LIMIT, method = "GET", data = null ) ->

				params[ "action" ] = action
				params[ "limit" ] = limit
				params[ "offset" ] = offset

				$http
					method: method
					url: "admin-ajax.php"
					params: params
					data: data
				.success ( data, status, headers, config ) ->
					$rootScope.$broadcast event, data
				.error ( data, status, headers, config ) ->
					$rootScope.$broadcast "error", data

		])

	angular.bootstrap document.getElementById( "wordliftApp" ), [ "wordliftApp" ]