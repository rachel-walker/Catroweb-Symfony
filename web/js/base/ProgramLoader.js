var ProgramLoader = function (container, url, column_max) {
  var self = this;
  self.container = container;
  self.url = url;
  self.default_rows = 2;
  self.columns_min = 3; // before changing these values, have a look at '.programs{.program{width:.%}}' in 'brain.less' first
  self.columns_max = (typeof column_max === "undefined") ? 9 : column_max; // before changing these values, have a look at '.programs{.program{width:.%}}' in 'brain.less' first
  self.download_limit = self.default_rows * self.columns_max;
  self.loaded = 0;
  self.visible = 0;
  self.visible_steps = 0;
  self.showAllPrograms = false;
  self.windowWidth = $(window).width();

  self.init = function() {
    $.get(self.url, { limit: self.download_limit, offset: self.loaded}, function(data) {
      if(data.CatrobatProjects.length == 0 || data.CatrobatProjects == undefined) {
        $(self.container).find('.programs').append('<div class="no-programs">There are currently no programs.</div>'); //todo: translate
        return;
      }
      self.setup(data);
    });
  };

  self.initProfile = function(user_id) {
    self.showAllPrograms = true;
    $.get(self.url, { limit: self.download_limit, offset: self.loaded, user_id: user_id }, function(data) {
      if(data.CatrobatProjects.length == 0 || data.CatrobatProjects == undefined) {
        $(self.container).find('.programs').append('<div class="no-programs">There are currently no programs.</div>'); //todo: translate
        return;
      }
      self.setup(data);
    });
  };

  self.initSearch = function(query) {
    $.get(self.url, { q: query, limit: self.download_limit*2, offset: self.loaded }, function(data) {
      var searchResultsText = $('#search-results-text');
      if(data.CatrobatProjects.length == 0 || data.CatrobatProjects == undefined) {
        searchResultsText.addClass('no-results');
        searchResultsText.find('span').text(0);
        return;
      }
      searchResultsText.find('span').text(data.CatrobatInformation.TotalProjects);
      self.setup(data);
      self.showMorePrograms();
      self.searchPageLoadDone = true; // fix for search.feature: 'I press enter "#searchbar"'
    });
  };

  self.setup = function(data) {
    if(!self.showAllPrograms) {
      $(self.container).append('' +
        '<div class="button-show-placeholder">' +
          '<div class="button-show-more img-load-more"></div>' +
          '<div class="button-show-ajax img-load-ajax"></div>' +
        '</div>');
    }
    self.loadProgramsIntoContainer(data);
    self.showMoreListener();
    self.setDefaultVisibility();
    $(window).resize(function() {
      if(self.windowWidth == $(window).width())
        return;
      self.setDefaultVisibility();
      self.windowWidth = $(window).width();
    });
  };

  self.loadProgramsIntoContainer = function(data) {
    var programs = data.CatrobatProjects;
    for(var i=0; i < programs.length; i++) {
      var div = null;

      // Extend this for new containers...
      switch(self.container) {
        case '#newest':
        case '#search-results':
          div = '<div><div class="img-time-small"></div>' + programs[i].UploadedString + '</div>';
          break;
        case '#myprofile-programs':
        case '#user-programs':
          div = '<div>' + programs[i].UploadedString + '</div>';
          break;
        case '#mostDownloaded':
          div = '<div><div class="img-download-small"></div>' + programs[i].Downloads + '</div>';
          break;
        case '#mostViewed':
          div = '<div><div class="img-view-small"></div>' + programs[i].Views + '</div>';
          break;
        default:
          if($(self.container).hasClass('starterDownloads'))
            div = '<div><div class="img-download-small"></div>' + programs[i].Downloads + '</div>';
          else
            div = '<div>unknown</div>';
      }

      var program_link = data.CatrobatInformation.BaseUrl + programs[i].ProjectUrl;

      var program = $(
        '<div class="program" id="program-'+ programs[i].ProjectId +'">'+
          '<div onclick="window.location.href = \''+ program_link + '\'">'+
            '<div><img src="' + data.CatrobatInformation.BaseUrl + programs[i].ScreenshotSmall +'"></div>'+
            '<div class="program-name"><b>'+ programs[i].ProjectName +'</b></div>'+
            div +
          '</div>'+
        '</div>'
      );

      $(self.container).find('.programs').append(program);

      if(self.container == '#myprofile-programs')
        $(program).prepend('<div id="delete-'+ programs[i].ProjectId +'" class="img-delete" onclick="profile.deleteProgram('+ programs[i].ProjectId +')"></div>');
    }
    self.loaded += programs.length;
  };

  self.showMorePrograms = function() {
    var programs_in_container = $(self.container).find('.program');

    $(programs_in_container).hide();
    for(var i = 0; i < self.visible + self.visible_steps; i++) {
      if(programs_in_container[i] == undefined) {
        $(self.container).find('.button-show-more').hide();
        break;
      }
      $(programs_in_container[i]).show();
    }

    if(self.loaded < self.visible + self.visible_steps)
      $(self.container).find('.button-show-more').hide();
    else
      $(self.container).find('.button-show-more').show();

    self.visible = i;
  };

  self.setDefaultVisibility = function() {
    if(self.showAllPrograms)
      return;

    var programs_in_row = parseInt($(window).width() / $('.program').width());
    if(programs_in_row < self.columns_min) programs_in_row = self.columns_min;
    if(programs_in_row > self.columns_max) programs_in_row = self.columns_max;

    var programs_in_container = $(self.container).find('.program');

    $(programs_in_container).hide();
    for(var i=0; i < self.default_rows * programs_in_row; i++) {
      $(programs_in_container[i]).show();
    }

    self.visible = i;
    self.visible_steps = i;

    if(self.loaded < self.visible)
      $(self.container).find('.button-show-more').hide();
    else
      $(self.container).find('.button-show-more').show();
  };

  self.showMoreListener = function() {
    $(self.container + ' .button-show-more').click(function() {

      if(self.visible + self.visible_steps <= self.loaded)
        self.showMorePrograms();
      else {
        $(self.container).find('.button-show-more').hide();
        $(self.container).find('.button-show-ajax').show();
        // on loadUserPrograms... set user_id as parameter
        $.get(self.url, { limit: self.download_limit, offset: self.loaded }, function(data) {
          if((data.CatrobatProjects.length == 0 || data.CatrobatProjects == undefined) && self.loaded <= self.visible) {
            $(self.container).find('.button-show-ajax').hide();
            return;
          }

          self.loadProgramsIntoContainer(data);
          self.showMorePrograms();

          $(self.container).find('.button-show-ajax').hide();
        });
      }

    });
  };
};