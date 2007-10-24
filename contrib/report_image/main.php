<?php
/**
 * Name: Report Images
 * Author: ATravelingGeek (atg@atravelinggeek.com
 * Link: http://atravelinggeek.com/
 * License: GPLv2
 * Description: Report images as dupes/illegal/etc
 * Version 0.2a - See change log below
 * October 24, 2007
 */
 
class RemoveReportedImageEvent extends Event {
	var $id;

	public function RemoveReportedImageEvent($id) {
		$this->id = $id;
	}
}

class AddReportedImageEvent extends Event { 
	var $reporter_name;
	var $image_id;
	var $reason_type;
	var $reason;
	public function AddReportedImageEvent($image_id, $reporter_name, $reason_type, $reason) {
		$this->reporter_name = $reporter_name;
		$this->image_id = $image_id;
		$this->reason_type = $reason_type;
		$this->reason = $reason;
	}
}

class ReportImage extends Extension {
	public function receive_event($event) {
	
	if(is_null($this->theme)) $this->theme = get_theme_object("ReportImage", "ReportImageTheme");
	
	if(is_a($event, 'InitExtEvent')) {
			global $config;
			if($config->get_int("ext_ReportImage_version") < 1) {
				$this->install();
			}
		}
		
		if(is_a($event, 'PageRequestEvent') && ($event->page_name == "ReportImage")) {
			global $user;
				if($event->get_arg(0) == "add") {
					if(isset($_POST['image_id']) && isset($_POST['reason_type']) && isset($_POST['reason'])) {
						send_event(new AddReportedImageEvent($_POST['image_id'], $event->user->name, $_POST['reason_type'], $_POST['reason']));
						global $page;
						$page->set_mode("redirect");
						$page->set_redirect(make_link("post/view/".int_escape($_POST['image_id'])));
					}
				}
				else if($event->get_arg(0) == "remove") {
					if(isset($_POST['id'])) {
						send_event(new RemoveReportedImageEvent($_POST['id']));
						global $page;
						$page->set_mode("redirect");
						$page->set_redirect(make_link("admin"));
					}
				}
		}
		
		if(is_a($event, 'AdminBuildingEvent')) {
			global $page;
			$this->theme->display_reported_images($page, $this->get_reported_images());
		}
		
			if(is_a($event, 'AddReportedImageEvent')) {
			$this->add_reported_image($event->image_id, $event->reporter_name, $event->reason_type, $event->reason);
		}

		if(is_a($event, 'RemoveReportedImageEvent')) {
			$this->remove_reported_image($event->id);
		}
		
		if(is_a($event, 'DisplayingImageEvent')) {
			global $user;
			global $config;
			if(!$config->get_bool('report_image_anon') && $user->is_anonymous()) {
				// Show nothing
			} else {
				$this->theme->display_image_banner($event->page, $event->image->id);
			}
		}

		if(is_a($event, 'SetupBuildingEvent')) {
			$sb = new SetupBlock("Report Image Options");
			$sb->add_bool_option("report_image_anon", "Allow anonymous image reporting: ");
//			I'll add this feature in soonish if anyone wants it.
//			$sb->add_label("<br>");
//			$sb->add_bool_option("report_image_sgow_thumbs", "Show thumbnails in admin panel: ");
			$event->panel->add_block($sb);
		}
		
	}
	
	protected function install() {
		global $database;
		global $config;
		if($config->get_int("ext_ReportImage_version") < 1) {
			$database->Execute("CREATE TABLE ReportImage (
				id int(11) NOT NULL auto_increment,
				image_id int(11) default NULL,
				reporter_name varchar(32) default NULL,
				reason_type varchar(255) default NULL,
				reason varchar(255) default NULL,
				PRIMARY KEY (id)
			)");
			$config->set_int("ext_ReportImage_version", 1);
		}
	}

	
	// DB funness
	
		public function get_reported_images() {
		// FIXME: many
		global $database;
		$reportedimages = $database->db->GetAll("SELECT * FROM ReportImage");
		if($reportedimages) {return $reportedimages;}
		else {return array();}
		}
		
		public function get_reported_image($id) {
		global $database;
		return $database->db->GetRow("SELECT * FROM ReportImage WHERE id = ?", array($id));
	}

	public function add_reported_image($image_id, $reporter_name, $reason_type, $reason) {
		global $database;
		$database->Execute(
				"INSERT INTO ReportImage (image_id, reporter_name, reason_type, reason) VALUES (?, ?, ?, ?)",
				array($image_id, $reporter_name, $reason_type, $reason));
	}

	public function remove_reported_image($id) {
		global $database;
		$database->Execute("DELETE FROM ReportImage WHERE id = ?", array($id));
	}
		
}
add_event_listener(new ReportImage(), 29); // Not sure what I'm in before.

//  ===== Changelog =====
// * Version 0.2a - 10/24/07 - Fixed some SQL issues. I will make sure to test before commiting :)
// * Version 0.2 - 10/24/07 - First public release.

?>