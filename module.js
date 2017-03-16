M.block_custom_progress = {
    custom_progressBarLast: new Array(),
    locked: false,

    init: function (YUIObject, instances, users) {
        var instance;
        var user;
        for (instance = 0; instance < instances.length; instance++) {
            for (user = 0; user < users.length; user++) {
                this.custom_progressBarLast[instances[instance] + '-' + users[user]] = 'info';
            }
        }
        this.addEvent(window, 'resize', function(e) {M.block_custom_progress.setupScrolling(YUIObject);});
    },

    showInfo: function (instance, user, id) {
        if (this.locked) {
            return;
        }
        var last = this.custom_progressBarLast[instance + '-' + user];
        document.getElementById('custom_progressBarInfo' + instance + '-' + user + '-' + last).style.display = 'none';
        document.getElementById('custom_progressBarInfo' + instance + '-' + user + '-' + id).style.display = 'block';
        this.custom_progressBarLast[instance + '-' + user] = id;
    },

    showAll: function (instance, user) {
        var infoBlocks = document.getElementsByClassName('custom_progressEventInfo');
        var i;
        var searchString = 'custom_progressBarInfo' + instance + '-' + user + '-';
        var searchStringLength = searchString.length;
        for (i = 0; i < infoBlocks.length; i++) {
            if (infoBlocks[i].id.substring(0,searchStringLength) == searchString) {
                infoBlocks[i].style.display = 'block';
            }
        }
        document.getElementById(searchString + 'info').style.display = 'none';
        this.locked = true;
    },

    setupScrolling: function(YUIObject) {
        var barContainers = document.getElementsByClassName('barContainer');
        var i;
        var leftArrows;
        var rightArrows;
        var nowIcons;
        for (i = 0; i < barContainers.length; i++) {
            nowIcons = barContainers[i].getElementsByClassName('nowicon');
            if(nowIcons.length > 0) {
                barContainers[i].scrollLeft = nowIcons[0].offsetLeft - (barContainers[i].offsetWidth / 2);
            }
            leftArrows = barContainers[i].getElementsByClassName('left-arrow-svg');
            rightArrows = barContainers[i].getElementsByClassName('right-arrow-svg');
            if(leftArrows.length > 0) {
                this.checkArrows(barContainers[i]);
                this.addEvent(leftArrows[0].firstChild, 'click', function(e) {M.block_custom_progress.scrollContainer(e.target.parentNode, -1); e.target.stopPagination();})
                this.addEvent(rightArrows[0].firstChild, 'click', function(e) {M.block_custom_progress.scrollContainer(e.target.parentNode, 1); e.target.stopPagination();})
            }
        }
    },

    checkArrows: function(container) {
        var leftArrow = container.getElementsByClassName('left-arrow-svg')[0];
        var rightArrow = container.getElementsByClassName('right-arrow-svg')[0];
        var scrolled = container.scrollLeft;
        var scrollWidth = container.scrollWidth - container.offsetWidth;
        var threshold = 10;
        var buffer = 5;

        if (scrolled > threshold) {
            leftArrow.style.display = 'block';
            leftArrow.style.left = (scrolled + buffer) + 'px';
        } else {
            leftArrow.style.display = 'none';
        }

        if (scrollWidth > threshold && scrolled < scrollWidth - threshold) {
            rightArrow.style.display = 'block';
            rightArrow.style.right = (buffer - scrolled) + 'px';
        } else {
            rightArrow.style.display = 'none';
        }
    },

    scrollContainer: function(arrow, direction) {
        var container = arrow.parentNode;
        var amount = direction * container.scrollWidth * 0.15;
        container.scrollLeft += amount;
        M.block_custom_progress.checkArrows(container);
    },

    addEvent: function(target, evt, func) {
        if (target.addEventListener) {
            target.removeEventListener(evt, func);
            target.addEventListener(evt, func);
        }
        else if (target.attachEvent) {
            target.detachEvent('on' + evt, func);
            target.attachEvent('on' + evt, func);
        }
    }
};